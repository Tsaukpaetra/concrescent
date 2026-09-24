<?php

namespace CM3_Lib\Action\Account\Session;

use CM3_Lib\models\contact_session;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\CurrentUserInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action to read a specific session.
 */
final class Read
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
        private CurrentUserInfo $user
    )
    {
    }

    /**
     * Action.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array $params Route parameters
     *
     * @return ResponseInterface The response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $contact_id = $this->user->GetContactId();
        $timestamp = (int)$params['token_timestamp'];

        // Retrieve the specific session using the composite key
        $result = $this->contact_session->GetByID(
            [
                'contact_id' => $contact_id, 
                'token_timestamp' => $timestamp
            ],
            [
                'contact_id',
                'token_timestamp',
                'description',
                'user_agent',
                'ip_address',
                'session_type',
                'metadata',
                'date_created',
                'date_modified'
            ]
        );

        if ($result === false) {
            throw new HttpNotFoundException($request, "Session not found.");
        }

        // Security check: Ensure the session belongs to the authenticated user
        // (Redundant if GetByID uses contact_id in the query, but kept for architectural consistency)
        if ((int)$result['contact_id'] !== $contact_id) {
            throw new HttpBadRequestException($request, "Unauthorized access to session.");
        }

        // Decode metadata if it was stored as a JSON string in the DB
        if (isset($result['metadata']) && is_string($result['metadata'])) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }

        return $this->responder->withJson($response, $result);
    }
}