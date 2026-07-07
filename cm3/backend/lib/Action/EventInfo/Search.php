<?php

namespace CM3_Lib\Action\EventInfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\eventinfo;
use CM3_Lib\util\TokenGenerator;
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
    public function __construct(private Responder $responder, private eventinfo $eventinfo, private TokenGenerator $TokenGenerator)
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
        $perms = $request->getAttribute('perms');
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array();
        if (!$perms->EventPerms->isGlobalAdmin()) {
            //Determine which (if any) events they have admin permissions for
            $CurrentPermissions = $this->TokenGenerator->loadPermissions($request->getAttribute('contact_id'));
            $EventsWithPerms = array_keys(array_filter($CurrentPermissions->EventPerms, function($value) {
                return !$value->EventPerms->isNoPermission();
            }));

            if(!empty($EventsWithPerms)) $whereParts[] =new SearchTerm('id', $EventsWithPerms, 'IN');
            $whereParts[] = new SearchTerm('active', 1, TermType: 'OR');
        }

        $order = array('date_end' => false);

        $page      = ($request->getQueryParams()['page']?? 0 > 0) ? $request->getQueryParams()['page'] : 1;
        $limit     = $request->getQueryParams()['itemsPerPage']?? -1; // Number of posts on one page
        $offset      = ($page - 1) * $limit;
        if ($offset < 0) {
            $offset = 0;
        }


        // Invoke the Domain with inputs and retain the result
        $data = $this->eventinfo->Search(array(), $whereParts, $order, $limit, $offset);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
