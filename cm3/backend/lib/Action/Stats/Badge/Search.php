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
            //If none were specified, specify them all (including inactive)
            if(empty($badge_type_ids)){
                $badge_type_ids = $this->badgeinfo->GetValidTypeIdsForContext($context_code, false);
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

            // Align hole-filling: each badge type should have periods up to
            // the max period found across ALL sources for that badge type.
            // Collect max period per badge type from the merged data.
            $badgeMaxPeriod = [];
            $badgeMinPeriod = [];
            foreach ($statsByPeriod as $key => $row) {
                $btId = $row['badge_type_id'];
                $p = $row['period'];
                if (!isset($badgeMaxPeriod[$btId]) || $p > $badgeMaxPeriod[$btId]) {
                    $badgeMaxPeriod[$btId] = $p;
                }
                if (!isset($badgeMinPeriod[$btId]) || $p < $badgeMinPeriod[$btId]) {
                    $badgeMinPeriod[$btId] = $p;
                }
            }

            // Convert MySQL format to PHP format for DateTime operations
            $dateFormat = $this->statsgen->getDateFormatFromMySQLFormat($period);
            $interval = $this->statsgen->getIntervalFromFormat($period);

            // For each badge type, extend periods to the max if sources ended earlier.
            foreach ($badgeMaxPeriod as $btId => $maxPeriod) {
                if (!isset($badgeMinPeriod[$btId])) continue;

                $startDate = \DateTime::createFromFormat('!' . $dateFormat, $badgeMinPeriod[$btId]);
                $endDate = \DateTime::createFromFormat('!' . $dateFormat, $maxPeriod);

                if (!$startDate || !$endDate) continue;
                if ($endDate < $startDate) continue;

                $existingPeriods = [];
                foreach ($statsByPeriod as $key => $row) {
                    if ($row['badge_type_id'] == $btId) {
                        $existingPeriods[$row['period']] = $key;
                    }
                }

                $currentDate = clone $startDate;
                while ($currentDate <= $endDate) {
                    $periodKey = $currentDate->format($dateFormat);
                    if (!isset($existingPeriods[$periodKey])) {
                        $statsKey = $context_code . '|' . $btId . '|' . $periodKey;
                        $statsByPeriod[$statsKey] = [
                            'context_code'       => $context_code,
                            'badge_type_id'      => $btId,
                            'period'             => $periodKey,
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
                    $currentDate->add(new \DateInterval($interval));
                }
            }

            // Flatten into the final results structure
            foreach ($statsByPeriod as $row) {
                $context_code = $row['context_code'];
                $badgeTypeId  = $row['badge_type_id'];
                unset($row['context_code']);
                // Keep badge_type_id for the cumulative loop below
                // Append to context → badge type → flat list
                if (!isset($results[$context_code][$badgeTypeId])) {
                    $results[$context_code][$badgeTypeId] = [];
                }
                $results[$context_code][$badgeTypeId][] = $row;
            }

            // Compute pre-range seeds per badge type (once, not per source) so
            // cumulative totals represent all-time counts from the start of the range.
            // Initialize seeds for ALL selected badge types first so the injection
            // and cumulative loop always have a key to look up.
            $preRangeSeeds = []; // ['badgeTypeId' => ['paid' => N, 'total' => N, 'printed' => N, 'checked_in' => N]]
            foreach ($badge_type_ids as $btId) {
                $preRangeSeeds[$btId] = ['paid' => 0, 'total' => 0, 'printed' => 0, 'checked_in' => 0];
            }
            if (!empty($rangeStart) && !empty($badge_type_ids)) {
                $ids = array_map('intval', $badge_type_ids);
                $rangeDate = date_format($rangeStart, 'Y-m-d H:i:s');

                // Total seed: count ALL records before rangeStart
                $preCols = [];
                $preCols[] = new \CM3_Lib\database\SelectColumn('badge_type_id', true, JoinedTableAlias: $joinAlias);
                $preCols[] = new \CM3_Lib\database\SelectColumn('id', false, 'count(*)', 'cnt');
                if ($badge->HasColumn('date_created')) {
                    $preCols[] = new \CM3_Lib\database\SelectColumn('date_created', false);
                }
                // Each column that a WHERE clause references must be in SELECT
                if ($badge->HasColumn('payment_status')) {
                    $preCols[] = new \CM3_Lib\database\SelectColumn('payment_status', false, JoinedTableAlias: $joinAlias);
                }
                if ($badge->HasColumn('time_printed')) {
                    $preCols[] = new \CM3_Lib\database\SelectColumn('time_printed', false);
                }
                if ($badge->HasColumn('time_checked_in')) {
                    $preCols[] = new \CM3_Lib\database\SelectColumn('time_checked_in', false);
                }
                $preWhere = [new SearchTerm('date_created', $rangeDate, '<')];
                $preView = new \CM3_Lib\database\View($preCols, $join !== null ? [$join] : null);
                foreach ($badge->Search($preView, $preWhere) as $pr) {
                    $preRangeSeeds[(string)$pr['badge_type_id']]['total'] = (int)($pr['cnt'] ?? 0);
                }

                // Paid seed: count paid records (Completed/RefundedInPart) before rangeStart
                $paidWhere = [
                    new SearchTerm('date_created', $rangeDate, '<'),
                    new SearchTerm('payment_status', ['Completed', 'RefundedInPart'], 'in', JoinedTableAlias: $joinAlias),
                ];
                $paidView = new \CM3_Lib\database\View($preCols, $join !== null ? [$join] : null);
                foreach ($badge->Search($paidView, $paidWhere) as $pr) {
                    $preRangeSeeds[(string)$pr['badge_type_id']]['paid'] = (int)($pr['cnt'] ?? 0);
                }

                // Printed seed: count records with time_printed IS NOT NULL before rangeStart
                $printedWhere = [
                    new SearchTerm('date_created', $rangeDate, '<'),
                    new SearchTerm('time_printed', null, 'IS NOT'),
                ];
                $printedView = new \CM3_Lib\database\View($preCols, $join !== null ? [$join] : null);
                foreach ($badge->Search($printedView, $printedWhere) as $pr) {
                    $preRangeSeeds[(string)$pr['badge_type_id']]['printed'] = (int)($pr['cnt'] ?? 0);
                }

                // Checked-in seed: count records with time_checked_in IS NOT NULL before rangeStart
                $checkedInWhere = [
                    new SearchTerm('date_created', $rangeDate, '<'),
                    new SearchTerm('time_checked_in', null, 'IS NOT'),
                ];
                $checkedInView = new \CM3_Lib\database\View($preCols, $join !== null ? [$join] : null);
                foreach ($badge->Search($checkedInView, $checkedInWhere) as $pr) {
                    $preRangeSeeds[(string)$pr['badge_type_id']]['checked_in'] = (int)($pr['cnt'] ?? 0);
                }
            }

            // Inject pre-seed rows for badge types that have pre-range data
            // but no in-range data at all. Without this they'd be invisible.
            $seedPeriod = !empty($badgeMinPeriod) ? min($badgeMinPeriod) : '';
            if ($seedPeriod !== '') {
                $seedDate = \DateTime::createFromFormat('!' . $dateFormat, $seedPeriod);
                if ($seedDate) {
                    $seedPeriod = $seedDate->format($dateFormat);
                }
            }
            foreach ($preRangeSeeds as $badgeTypeId => $seeds) {
                $hasData = false;
                if (isset($results[$context_code][$badgeTypeId])) {
                    foreach ($results[$context_code][$badgeTypeId] as $r) {
                        if ($r['total_count'] || $r['paid_count'] || $r['printed_count'] || $r['checked_in_count']) {
                            $hasData = true;
                            break;
                        }
                    }
                    // Also check if a row for seedPeriod already exists
                    // (prevents duplicates when injection period matches in-range data)
                    $hasSeedPeriod = false;
                    foreach ($results[$context_code][$badgeTypeId] as $r) {
                        if (isset($r['period']) && $r['period'] === $seedPeriod) {
                            $hasSeedPeriod = true;
                            break;
                        }
                    }
                } else {
                    $hasSeedPeriod = false;
                }
                if (!$hasData && !$hasSeedPeriod && $seeds['total'] > 0) {
                    if (!isset($results[$context_code][$badgeTypeId])) {
                        $results[$context_code][$badgeTypeId] = [];
                    }
                    $results[$context_code][$badgeTypeId][] = [
                        'badge_type_id'      => $badgeTypeId,
                        'period'             => $seedPeriod,
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
            }

            // Sort periods and fix cumulative totals.
            // Seed each running total with its pre-range count so cumulative
            // represents all-time totals visible from the first period.
            foreach ($results as $contextCode => &$badgeTypes) {
                foreach ($badgeTypes as &$rows) {
                    // Sort periods ascending
                    usort($rows, function($a, $b) {
                        return $a['period'] < $b['period'] ? -1 : ($a['period'] > $b['period'] ? 1 : 0);
                    });

                    // Fix cumulative totals for all four column types
                    $countFields = [
                        'paid' => 'paid_count',
                        'printed' => 'printed_count',
                        'checked_in' => 'checked_in_count',
                        'total' => 'total_count',
                    ];
                    $totalFields = [
                        'paid' => 'paid_total',
                        'printed' => 'printed_total',
                        'checked_in' => 'checked_in_total',
                        'total' => 'total_total',
                    ];

                    $badgeTypeId = $rows[0]['badge_type_id'] ?? '';
                    $seeds = $preRangeSeeds[$badgeTypeId] ?? ['paid' => 0, 'total' => 0, 'printed' => 0, 'checked_in' => 0];

                    foreach ($countFields as $key => $countField) {
                        $totalField = $totalFields[$key];
                        $running = $seeds[$key] ?? 0;
                        foreach ($rows as &$row) {
                            $running += $row[$countField];
                            $row[$totalField] = $running;
                        }
                    }
                }
            }
            unset($rows);
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
