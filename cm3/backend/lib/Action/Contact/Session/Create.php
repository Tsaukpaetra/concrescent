<?php

namespace CM3_Lib\Action\Contact\Session;

use CM3_Lib\models\contact_session;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\CurrentUserInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action to create a new session.
 */
final class Create
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
     *
     * @return ResponseInterface The response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $data = (array)$request->getParsedBody();
        $contact_id = (int)$params['contact_id'];

        // Prepare data for insertion
        $new_session = [
            'contact_id'      => $contact_id,
            'token_timestamp' => time(),
            'description'     => $data['description'] ?? 'New Session',
            'user_agent'      => $request->getHeaderLine('User-Agent'),
            'ip_address'      => $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0',
            'session_type'    => $data['session_type'] ?? 'standard',
            'metadata'        => isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];

        // Perform the creation via the table model
        $result = $this->contact_session->Create($new_session);

        if ($result === false) {
            return $this->responder->withJson($response, ['error' => 'Could not create session'], 500);
        }

        return $this->responder->withJson($response, $result, 201);
    }
}