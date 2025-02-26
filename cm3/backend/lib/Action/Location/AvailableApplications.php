<?php

namespace CM3_Lib\Action\Location;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\models\application\badgetype as g_badge_type;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\group as g_group;
use CM3_Lib\models\application\assignment as g_assignment;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class AvailableApplications
{
    /**
     * The constructor.
     *
     */
    public function __construct(
        private Responder $responder,
        private g_group $g_group,
        private g_badge_type $g_badge_type,
        private g_badge_submission $g_badge_submission,
        private g_assignment $g_assignment,
        private CurrentUserInfo $CurrentUserInfo,
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array) $request->getParsedBody();
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        // // Invoke the Domain with inputs and retain the result
        // $data = $this->submission->Search(array(), $whereParts, $order, $limit, $offset);
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';


        $pg = $this->badgeinfo->parseQueryParamsPagination($qp,'assignments', false);
        $totalRows = 0;

        // Invoke the Domain with inputs and retain the result
        $data = $this->SearchGroupApplicationsText($qp['context_code'] ?? '', $find, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);


        //Add the display_name
        foreach ($data as &$value)
        {
            switch ($value['name_on_badge'])
            {
                case 'Fandom Name Large, Real Name Small':
                    $value['display_name'] = trim(($value['fandom_name'] ?? '') . ' (' . $value['real_name'] . ')');
                    break;
                case 'Real Name Large, Fandom Name Small':
                    $value['display_name'] = trim($value['real_name'] . ' (' . ($value['fandom_name'] ?? '') . ')');
                    break;
                case 'Fandom Name Only':
                    $value['display_name'] = $value['fandom_name'] ?? '';
                    break;
                case 'Real Name Only':
                    $value['display_name'] = $value['real_name'];
                    break;
            }
            $value['badge_id_display'] = $value['context_code'] . $value['display_id'];
        }
        $response = $response->withHeader('X-Total-Rows', (string) $totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }

    //Rest is ripped from badgeinfo with deletion
    public function SearchGroupApplicationsText($context, string $searchText, $order, $limit, $offset, &$totalRows)
    {
        $whereParts =
            empty($searchText) ? [
                new SearchTerm('assignment_count', 0, '>'),
                new SearchTerm('application_status', ['PendingAcceptance', 'Accepted'], 'IN')
            ] :
            array(
                new SearchTerm('application_status', ['PendingAcceptance', 'Accepted'], 'IN'),
                new SearchTerm('real_name', $searchText, Raw: 'MATCH(' . $this->g_badge_submission->dbTableName() . '.`real_name`,' . $this->g_badge_submission->dbTableName() . '.`fandom_name`) AGAINST (? IN NATURAL LANGUAGE MODE) ')
            );
        $wherePartsSimpler = [
            new SearchTerm('assignment_count', 0, '>'),
            new SearchTerm('application_status', ['PendingAcceptance', 'Accepted'], 'IN'),
            new SearchTerm(
                '',
                '',
                subSearch: [
                    new SearchTerm('real_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('fandom_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('email_address', '%' . $searchText . '%', 'LIKE', 'OR', JoinedTableAlias: 'con'),
                ]
            )
        ];

        //If it looks like they specified a badge ID, add that to the search term
        preg_match_all('/(?\'context_code\'[a-zA-Z]{0,3})(?\'display_id\'\d+)/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch)
        {
            $whereParts[] = new SearchTerm('', '', TermType: 'OR', subSearch: array(
                new SearchTerm('context_code', $exactSearch['context_code'], JoinedTableAlias: 'grp'),
                new SearchTerm('display_id', $exactSearch['display_id'], )
            ));
        }

        //If it looks like an internal ID, add  it to the search term
        preg_match_all('/^(?\'id\'\d+)$/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch)
        {
            $whereParts[] = new SearchTerm('', '', TermType: 'OR', subSearch: array(
                new SearchTerm('id', $exactSearch['id'], ),
                //TODO: This isn't working for some reason
                //new SearchTerm('payment_id', $exactSearch['id'])
            ));
        }

        $result = $this->SearchGroupApplications($context, $whereParts, $order, $limit, $offset, $totalRows, false);
        //If we got nothing, switch to a simpler search
        if (count($result) == 0)
        {
            $result = $this->SearchGroupApplications($context, $wherePartsSimpler, $order, $limit, $offset, $totalRows, false);
        }
        return $result;
    }
    public function SearchGroupApplications($context, $terms, ?array $order = null, int $limit = -1, int $offset = 0, &$totalRows = null, $full = false)
    {
        // Invoke the Domain with inputs and retain the result
        $g_bv = $this->groupApplicationBadgeView();
        $g_terms = $this->badgeinfo->AdjustSearchTerms($terms, $g_bv);
        //Add to the group search if context specified
        if (!empty($context))
        {
            $g_terms[] = new SearchTerm('context_code', $context, is_null($context) ? 'IS' : '=', JoinedTableAlias: 'grp');
        }

        // $this->g_badge_submission->debugThrowBeforeSelect = true;
        $g_data = $this->g_badge_submission->Search($g_bv, $g_terms, $order, $limit, $offset, $totalRows);
        return $g_data;
    }

    public function groupApplicationBadgeView()
    {
        $result = new View(
            array(
                'id',
                'display_id',
                'real_name',
                'fandom_name',
                'name_on_badge',
                new SelectColumn('context_code', JoinedTableAlias: 'grp'),
                new SelectColumn('assignment_count'),
                new SelectColumn('AssignmentCount', EncapsulationFunction: 'ifnull(?,0)', Alias: 'assignments', JoinedTableAlias: 'ac'),
                new SelectColumn('application_status'),
                new SelectColumn('badge_type_id'),
                new SelectColumn('payment_status'),
                new SelectColumn('name', Alias: 'badge_type_name', JoinedTableAlias: 'typ'),

            ),
            array(
                new Join(
                    $this->g_badge_type,
                    array(
                        'id' => new SearchTerm('badge_type_id', null),
                    ),
                    alias: 'typ'
                ),
                new Join(
                    $this->g_group,
                    array(
                        'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ'),
                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                    ),
                    alias: 'grp'
                ),
                new Join(
                    $this->g_assignment,
                    ['application_id' => 'id'],
                    'LEFT',
                    'ac',
                    [
                        new SelectColumn('application_id', true),
                        new SelectColumn('id', false, 'count(?)', 'AssignmentCount')
                    ]
                )
            )
        );

        return $result;
    }
}
