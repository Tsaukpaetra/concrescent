<?php

namespace CM3_Lib\Action\Stats\Badge;

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

            $badge_groups = array_filter(explode(',', $qp['badge_groups'] ?? ''), function ($v) {
                return !empty($v);
            });
            // Invoke the Domain with inputs and retain the result
            $results[$context_code] = $this->statsgen->getBadgeStats(
                $this->badgeFromContext($context_code),
                $badge_type_ids,
                $period,
                $badge_groups,
                rangeStart: !empty($qp['range_start']) ? date_create($qp['range_start']) : null,
                rangeEnd: !empty($qp['range_end']) ? date_create($qp['range_end']): null,
                fillHoles: true,
            );

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
                return $this->g_badge_submission;
        }
    }
}
