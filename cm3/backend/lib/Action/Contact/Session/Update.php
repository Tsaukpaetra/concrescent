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
 * Action.
 */
final class Update
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
        // Extract form data
        $data = (array)$request->getParsedBody();
        
        $contact_id = (int)$params['contact_id'];
        $timestamp = (int)$params['token_timestamp'];

        // Verify ownership before allowing update
        $current = $this->contact_session->GetByID(
            ['contact_id' => $contact_id, 'token_timestamp' => $timestamp],
            ['contact_id']
        );

        if ($current === false) {
            throw new HttpNotFoundException($request, "Session not found or unauthorized.");
        }

        // Prepare data for the model Update method
        // We explicitly define only the keys allowed to be changed
        $updateData = [
            'contact_id'      => $contact_id,
            'token_timestamp' => $timestamp,
        ];

        if (isset($data['description'])) {
            $updateData['description'] = (string)$data['description'];
        }

        if (isset($data['metadata'])) {
            // Ensure metadata is encoded as a JSON string for the DB if it's passed as an array
            $meta = $data['metadata'];
            $updateData['metadata'] = is_array($meta) ? json_encode($meta) : $meta;
        }

        // Invoke the Domain Update
        $result = $this->contact_session->Update($updateData);

        if ($result === false) {
            return $this->responder->withJson($response, ['success' => false], 500);
        }

        // Return the updated state or success indicator
        return $this->responder->withJson($response, [
            'success' => true,
            'contact_id' => $contact_id,
            'token_timestamp' => $timestamp
        ]);
    }
}