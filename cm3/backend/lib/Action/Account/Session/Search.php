<?php

namespace CM3_Lib\Action\Account\Session;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\contact_session;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\util\badgeinfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action to list sessions for the current user.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param contact_session $contact_session The model
     * @param CurrentUserInfo $user The current user info
     */
    public function __construct(
        private Responder $responder, 
        private contact_session $contact_session,
        private CurrentUserInfo $user,
        private badgeinfo $badgeinfo
    )
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
        $contact_id = $this->user->GetContactId();
        $showEphemeral = filter_var($request->getQueryParams()['showEphemeral'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $whereParts = array(
            new SearchTerm('contact_id', $contact_id),
            $showEphemeral ? null : new SearchTerm('session_type','ephemeral_login_only','!=')
        );

        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';
        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, 'token_timestamp', defaultSortDesc:true);
        $totalRows = 0;

        // Fetch sessions for this contact
        $data = $this->contact_session->Search(
            array(), 
            $whereParts, 
            $pg['order'], 
            $pg['limit'], 
            $pg['offset'],
            $totalRows
        );
        
        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder->withJson($response, $data);
    }
}