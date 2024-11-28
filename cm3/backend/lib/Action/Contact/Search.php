<?php

namespace CM3_Lib\Action\Contact;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\contact;
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
    public function __construct(private Responder $responder, private contact $contact,
    private badgeinfo $badgeinfo)
    {
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
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
          new SearchTerm('real_name', '%' . $find .'%', 'LIKE'),
          new SearchTerm('email_address', '%' . $find .'%', 'LIKE', 'OR'),
        );

        $order = array('id' => false);

        $page      = ($request->getQueryParams()['page']?? 0 > 0) ? $request->getQueryParams()['page'] : 1;
        $limit     = $request->getQueryParams()['itemsPerPage']?? -1; // Number of posts on one page
        $offset      = ($page - 1) * $limit;
        if ($offset < 0) {
            $offset = 0;
        }

        //Interpret order parameters
        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortDesc:true);
        $totalRows = 0;
        $data = $this->contact->Search(array(), $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
