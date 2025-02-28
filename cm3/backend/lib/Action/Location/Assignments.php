<?php

namespace CM3_Lib\Action\Location;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\assignment;
use CM3_Lib\models\application\submission;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;


/**
 * Action.
 */
final class Assignments
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private location $location,
        private assignment $assignment,
        private submission $submission,
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
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';

        //Do the param check if we've been given an ID
        if (!empty($params['id']))
        {
            $check = $this->location->GetByID($params['id'], ['event_id']);
            if ($check === false)
            {
                throw new HttpNotFoundException($request);
            }
            if ($check['event_id'] != $request->getAttribute('event_id'))
            {
                throw new HttpBadRequestException($request, 'Location does not belong to the current event!');
            }
        }


        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortColumn: 'id', defaultSortDesc: false);
        $totalRows = 0;
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
            empty($params['id']) ? null : new SearchTerm('location_id', $params['id'])
        );

        //Add in the assignments
        $data = $this->assignment->Search(
            new View([
                'application_id',
                'location_id',
                'category_id',
                'start_time',
                'end_time',
                new SelectColumn('real_name', JoinedTableAlias: 's'),
                new SelectColumn('fandom_name', JoinedTableAlias: 's'),
                new SelectColumn('name_on_badge', JoinedTableAlias: 's'),
                new SelectColumn('application_status', JoinedTableAlias: 's'),
            ], [
                new Join($this->submission, ['id' => 'application_id'], alias: 's'),
                empty($params['id']) ? new Join(
                    $this->location,
                    ['id' => 'location_id'],
                    subQSelectColumns:['id'],
                    subQSearchTerms: [new SearchTerm('event_id', $request->getAttribute('event_id'))]
                ) : null
            ]),
            $whereParts
        );

        //Add the display_name
        foreach ($data as &$value) {            
            switch ($value['name_on_badge']) {
                case 'Fandom Name Large, Real Name Small':
                    $value['display_name'] = trim(($value['fandom_name'] ??'') .' (' . $value['real_name'] . ')');
                    break;
                case 'Real Name Large, Fandom Name Small':
                    $value['display_name'] = trim($value['real_name'] .' (' . ($value['fandom_name']??'') . ')');
                    break;
                case 'Fandom Name Only':
                    $value['display_name'] = $value['fandom_name']??'';
                    break;
                case 'Real Name Only':
                    $value['display_name'] = $value['real_name'];
                    break;
            }
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
