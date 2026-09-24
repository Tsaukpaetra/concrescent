<?php

namespace CM3_Lib\Action\Contact\Session;

use CM3_Lib\models\contact_session;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\CurrentUserInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action to terminate a specific session.
 */
final class Delete
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
        $contact_id = (int)$params['contact_id'];
        $timestamp = (int)$params['token_timestamp'];

        // Verify the session exists and belongs to the user
        $session = $this->contact_session->GetByID(
            ['contact_id' => $contact_id, 'token_timestamp' => $timestamp],
            ['contact_id', 'token_timestamp']
        );

        if ($session === false) {
            //Idempotent, if it's not there, deleting it does nothing
            return $this->responder->withJson($response, ['success' => true]);
        }

        // Use the model's built-in termination method
        $result = $this->contact_session->terminateSession($contact_id, $timestamp);

        if (!$result) {
            return $this->responder->withJson($response, ['success' => false], 500);
        }

        return $this->responder->withJson($response, ['success' => true]);
    }
}