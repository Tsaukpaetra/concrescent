<?php

namespace CM3_Lib\Action\Application\Assignment;

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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Extract the form data from the request body
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';

        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortColumn: 'id', defaultSortDesc: false);
        $totalRows = 0;
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
            //new SearchTerm('active', 1)
        );


        // Invoke the Domain with inputs and retain the result
        $data = $this->assignment->Search(new View(
            [
                'id',
                'application_id',
                'location_id',
                'start_time',
                'end_time'
            ],
            [
                new Join($this->location, ['id' => 'location_id'], alias: 'l', subQSearchTerms: [
                    new SearchTerm('event_id', $request->getAttribute('event_id')),
                ]),
                new Join($this->submission, ['id' => 'assignment_id'], alias: 's', subQSearchTerms: [
                    new SearchTerm('event_id', $request->getAttribute('event_id')),
                ]),
            ]
        ), $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
