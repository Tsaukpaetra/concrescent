<?php

namespace CM3_Lib\Action\Staff\Position;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\Join;
use CM3_Lib\database\View;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\models\staff\department;
use CM3_Lib\models\staff\position;

use CM3_Lib\util\badgeinfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class All
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private position $position,
        private department $department,
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
        $qp = $request->getQueryParams();
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
          new SearchTerm('department_id', $params['department_id'])
        );



        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, 'id');
        $totalRows = 0;
        // Invoke the Domain with inputs and retain the result
        $data = $this->position->Search(new View(
            [
                new SelectColumn('id', Alias:'Department_Id', JoinedTableAlias:'d'),
                new SelectColumn('name', Alias:'Department_Name', JoinedTableAlias:'d'),
                new SelectColumn('description', Alias:'Department_Description', JoinedTableAlias:'d'),
                new SelectColumn('id', Alias:'Position_Id', JoinedTableAlias:'p'),
                new SelectColumn('name', Alias:'Position_Name', JoinedTableAlias:'p'),
                new SelectColumn('description', Alias:'Position_Description', JoinedTableAlias:'p'),
                new SelectColumn('is_exec', Alias:'Position_IsExec', JoinedTableAlias:'p'),
                new SelectColumn('active', Alias:'Position_Active', JoinedTableAlias:'p'),
            ],
            array(
                new Join($this->department, array(
                'id' => 'department_id',
                new SearchTerm('event_id', $request->getAttribute('event_id')),                
            ), alias: 'd'),)
        ), [],initialTableAlias:'p');


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
