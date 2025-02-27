<?php

namespace CM3_Lib\Action\LocationCategory;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\locationcoord;
use CM3_Lib\models\application\locationcategory;
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
        private locationcategory $locationcategory,
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
        $find = $qp['find'] ?? '';

        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortColumn: 'id', defaultSortDesc: false);
        $totalRows = 0;

        $whereParts = array(
            new SearchTerm('event_id', $request->getAttribute('event_id')),
            empty($find) ? null : new SearchTerm('', '', subSearch: [
                new SearchTerm('name', '%' . $find . '%', 'LIKE'),
                new SearchTerm('description', '%' . $find . '%', 'LIKE', 'OR'),
                new SearchTerm('notes', '%' . $find . '%', 'LIKE', 'OR'),
            ])
        );

        // Invoke the Domain with inputs and retain the result
        $data = $this->locationcategory->Search(new View([
            'id',
            'name',
            'color',
            'active',
            'description'
            
        ]), $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        $response = $response->withHeader('X-Total-Rows', (string) $totalRows);
        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
