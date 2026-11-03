<?php

namespace CM3_Lib\Action\Stats\Badge;

use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\attendee\badge as a_badge;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\submissionapplicant as g_badge;
use CM3_Lib\models\staff\badge as s_badge;
use CM3_Lib\util\statsgen;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Search
{
    public function __construct(
        private Responder $responder,
        private statsgen $statsgen,
        private a_badge $a_badge,
        private s_badge $s_badge,
        private g_badge $g_badge,
        private g_badge_submission $g_badge_submission,
        private badgeinfo $badgeinfo
    ) {
    }

    /**
     * Action.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response
     *
     * @return ResponseInterface The response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $qp = $request->getQueryParams();
        $period = $qp['period'] ?? '%Y-%m-%d';
        
        $results = [];
        
        // Pre-filter the array based on keys starting with "cx_"
        $contexts = array_reduce(
            array_keys($qp),
            function ($result, $key) use ($qp) {
                if (strpos($key, 'cx_') === 0) {
                    // Remove the "cx_" prefix from the key
                    $result[substr($key, 3)] = $qp[$key];
                }
                return $result;
            },
            array()
        );

        //If no contexts were given, give them all!!!
        if(empty($contexts)) {
            $contexts = array_fill_keys($this->badgeinfo->GetValidContexts(),"");
        }

        foreach ($contexts as $context_code => $badge_type_ids_delimited) {
            //Add in the badge types requested
            $badge_type_ids = array_filter(explode(',', $badge_type_ids_delimited ?? ''), function ($v) {
                return !empty($v);
            });
            //If none were specified, specify them all
            if(empty($badge_type_ids)){
                $badge_type_ids = $this->badgeinfo->GetValidTypeIdsForContext($context_code);
            }

            $badge = $this->badgeFromContext($context_code);

            $rangeStart = !empty($qp['range_start']) ? date_create($qp['range_start']) : null;
            $rangeEnd = !empty($qp['range_end']) ? date_create($qp['range_end']) : null;

            // Configure stats sources based on which columns the table actually has.
            // Attendee/Staff badges have all columns directly on the table.
            // Application submissions split the data: applicants table has
            // time_printed/time_checked_in/date_created, but badge_type_id and
            // payment_status live on the parent submissions table.
            $statsByPeriod = []; // merged by (contextCode, badgeTypeId, period)
            $statsSources = [];
            $join = null;
            $joinAlias = null;

            // For applications, join applicants → submissions to get badge_type_id.
            // Join OnColumns maps left-table-col => right-table-col, but Table.php
            // builds ON left.right = right.left (swaps them), so reverse the keys.
            if ($context_code !== 'A' && $context_code !== 'S') {
                $subs = $this->g_badge_submission;
                $join = new Join(
                    $subs,
                    ['id' => 'application_id'],
                    'INNER',
                    'sub'
                );
                $joinAlias = 'sub';
            }

            // Paid groups by payment_status so it's in SELECT; merge loop filters to paid statuses
            $statsSources[] = [
                'groupColumns' => ['payment_status'],
                'periodColumn' => 'date_created',
                'countField'   => 'paid_count',
                'totalField'   => 'paid_total',
                'paidStatuses' => ['Completed', 'RefundedInPart'],
            ];

            // Total counts all badges regardless of payment status
            $statsSources[] = [
                'groupColumns' => [],
                'periodColumn' => 'date_created',
                'countField'   => 'total_count',
                'totalField'   => 'total_total',
            ];

            // Printed only if the table has time_printed
            if ($badge->HasColumn('time_printed')) {
                $statsSources[] = [
                    'groupColumns' => [],
                    'periodColumn' => 'time_printed',
                    'countField'   => 'printed_count',
                    'totalField'   => 'printed_total',
                    'whereTerms'    => [new SearchTerm('time_printed', null, 'IS NOT')],
                ];
            }

            // Checked in only if the table has time_checked_in
            if ($badge->HasColumn('time_checked_in')) {
                $statsSources[] = [
                    'groupColumns' => [],
                    'periodColumn' => 'time_checked_in',
                    'countField'   => 'checked_in_count',
                    'totalField'   => 'checked_in_total',
                    'whereTerms'    => [new SearchTerm('time_checked_in', null, 'IS NOT')],
                ];
            }

            foreach ($statsSources as $source) {
                $rawStats = $this->statsgen->getBadgeStats(
                    $badge,
                    $badge_type_ids,
                    $period,
                    $source['groupColumns'],
                    whereTerms: $source['whereTerms'] ?? [],
                    rangeStart: $rangeStart,
                    rangeEnd: $rangeEnd,
                    fillHoles: true,
                    periodColumn: $source['periodColumn'],
                    join: $join,
                    joinAlias: $joinAlias,
                );

                foreach ($rawStats as $groupKey => $periodRows) {
                    $parts = explode('|', $groupKey);
                    $badgeTypeId = $parts[0];
                    $paymentStatus = $parts[1] ?? null;

                    foreach ($periodRows as $row) {
                        $rowPeriod = $row['period'];

                        // Ensure merged row exists for this (context, badgeType, period)
                        $key = $context_code . '|' . $badgeTypeId . '|' . $rowPeriod;
                        if (!isset($statsByPeriod[$key])) {
                            $statsByPeriod[$key] = [
                                'context_code'       => $context_code,
                                'badge_type_id'      => $badgeTypeId,
                                'period'             => $rowPeriod,
                                'paid_count'         => 0,
                                'paid_total'         => 0,
                                'printed_count'      => 0,
                                'printed_total'      => 0,
                                'checked_in_count'   => 0,
                                'checked_in_total'   => 0,
                                'total_count'        => 0,
                                'total_total'        => 0,
                            ];
                        }

                        // For the Paid source, only accumulate rows with paid statuses
                        if ($source['countField'] === 'paid_count') {
                            if (in_array($paymentStatus, $source['paidStatuses'])) {
                                $statsByPeriod[$key]['paid_count'] += (int)($row['count'] ?? 0);
                                $statsByPeriod[$key]['paid_total'] += (int)($row['total'] ?? 0);
                            }
                        } else {
                            $statsByPeriod[$key][$source['countField']] = (int)($row['count'] ?? 0);
                            $statsByPeriod[$key][$source['totalField']] = (int)($row['total'] ?? 0);
                        }
                    }
                }
            }

            // Flatten into the final results structure
            foreach ($statsByPeriod as $row) {
                $context_code = $row['context_code'];
                $badgeTypeId  = $row['badge_type_id'];
                unset($row['context_code'], $row['badge_type_id']);
                // Append to context → badge type → flat list
                if (!isset($results[$context_code][$badgeTypeId])) {
                    $results[$context_code][$badgeTypeId] = [];
                }
                $results[$context_code][$badgeTypeId][] = $row;
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $results);
    }

    function badgeFromContext(string $context_code)
    {
        switch ($context_code)
        {
            case 'A':
                return $this->a_badge;
            case 'S':
                return $this->s_badge;
            default:
                return $this->g_badge;
        }
    }
}
