<?php

namespace CM3_Lib\Action\Staff\Badge;

use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\models\staff\assignedposition;
use CM3_Lib\models\staff\department;
use CM3_Lib\models\staff\position;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private badgeinfo $badgeinfo,
        private department $department,
        private assignedposition $assignedposition,
        private position $position,
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
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';
        //TODO: Actually do something with submitted data. Also, provide some sane defaults


        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortDesc:true);
        $totalRows = 0;
        //Add in the questions requested
        $questionIds = array_filter(explode(',', $qp['questions']??''), function ($v) {
            return !empty($v);
        });
        // Invoke the Domain with inputs and retain the result
        $data = $this->badgeinfo->SearchBadgesText('S', $find, $pg['order'], $pg['limit'], $pg['offset'], $totalRows, $questionIds, $qp['filter'] ??'');

        //Add in assigned positions
        $pos = $this->assignedposition->Search(
            new View(
                array(
                    'staff_id','position_id','onboard_completed','onboard_meta','date_created','date_modified',
                    new SelectColumn('is_exec', JoinedTableAlias:'p'),
                    new SelectColumn('name', Alias:'position_text', JoinedTableAlias:'p'),
                    new SelectColumn('department_id', JoinedTableAlias:'p'),
                    new SelectColumn('name', Alias:'department_text', JoinedTableAlias:'d'),
                ),
                array(
                    new Join(
                        $this->position,
                        array('id'=>'position_id'),
                        alias:'p'
                    ),
                    new Join(
                        $this->department,
                        array('id'=>new SearchTerm('department_id', null, JoinedTableAlias:'p')),
                        alias:'d'
                    ),
                )
            ),
            array(
                new SearchTerm('staff_id', array_column($data,'id'),'in')
            )
        );
        //Prep lookup
        $staffixlu = array_flip(array_column($data,'id'));

        foreach ($pos as  $position) {
            $data[$staffixlu[$position['staff_id']]]['assigned_positions'][] = $position;
        }

        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
