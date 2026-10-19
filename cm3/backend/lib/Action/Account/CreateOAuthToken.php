<?php

namespace CM3_Lib\Action\Account;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use CM3_Lib\util\PermOAuth;
use CM3_Lib\util\TokenGenerator;
use CM3_Lib\util\BaseIntEncoder;
use CM3_Lib\models\admin\oauth_app;

use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpBadRequestException;

final class CreateOAuthToken
{
    public function __construct(private TokenGenerator $tokenGenerator, private Responder $responder, private oauth_app $oauth_app)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $parms): ResponseInterface
    {
        $indata = (array) $request->getParsedBody();
        $client_id = $parms['client_id'] ?? '';
        $scope = explode(' ',$indata['scope'] ?? '');
        $expires_in = $indata['expires_in'] ?? 0;
        $pkce_data = $indata['pkce_data'] ?? '';
        $redirect_uri = $indata['redirect_uri'] ?? '';

        //Client pre-checks:
        $oauth_app = $this->oauth_app->GetByID(BaseIntEncoder::decode($client_id), [
            'active',
            'redirect_uri'
        ]);
        if (!$oauth_app) {
            return $this->responseWithError($response, 'invalid_client', 'The application is not registered.');
        }
        if (!$oauth_app['active']) {
            return $this->responseWithError($response, 'invalid_client', 'The application is not active.');
        }

        $allowedUris = explode("\n", $oauth_app['redirect_uri'] ?? '');
        if (!$redirect_uri) {
            // If client has multiple registered URIs, they MUST specify which one they are using
            if (count($allowedUris) > 1) {
                return $this->responseWithError($response, 'invalid_request', 'Missing required parameter: redirect_uri.');
            }
            // Fallback to the single configured URI if they omitted it
            $redirect_uri = $allowedUris[0];
        } else {
            $isValid = false;
            if (in_array($redirect_uri, $allowedUris, true)) {
                $isValid = true;
            } else {
                // 2. Exact match failed. Check if the incoming URI is a localhost loopback
                $incomingParts = parse_url($redirect_uri);
                $incomingHost = $incomingParts['host'] ?? '';

                if ($incomingHost === 'localhost' || $incomingHost === '127.0.0.1') {
                    // Look through registered URIs to find a matching loopback baseline
                    foreach ($allowedUris as $registeredUri) {
                        $registeredParts = parse_url($registeredUri);
                        $registeredHost = $registeredParts['host'] ?? '';

                        // Check if schemes, hosts, and paths match, ignoring the port
                        if (
                            $incomingParts['scheme'] === ($registeredParts['scheme'] ?? null) &&
                            $incomingHost === $registeredHost &&
                            ($incomingParts['path'] ?? '') === ($registeredParts['path'] ?? '')
                        ) {
                            $isValid = true;
                            break;
                        }
                    }
                }
            }

            if (!$isValid) {
                return $this->responseWithError($response, 'invalid_request', 'The redirect_uri provided is invalid.');
            }
        }

        // 4. PKCE Data Structuring Verification (If present)
        if (!empty($pkceData)) {
            if (strpos($pkceData, ':') === false) {
                return $this->responseWithError($response, 'invalid_request', 'Malformed PKCE data structure.');
            }

            list($method, $challenge) = explode(':', $pkceData, 2);
            if ($method !== 'S256' && $method !== 'plain') {
                return $this->responseWithError($response, 'invalid_request', 'Unsupported PKCE transformation method.');
            }
            if(empty($challenge)){
                return $this->responseWithError($response, 'invalid_request', 'Unsupported PKCE transformation challenge.');
            }
        }

        $validscope = PermOAuth::toArray();

        //Ensure only valid scope are going to be added
        $oauthPerm = array_reduce($scope, function ($carry, $scope) use ($validscope) {
            if (isset($validscope[$scope])) {
                $carry->{'set' . $scope}(true);
            }
            return $carry;
        }, new PermOAuth(0));

        if ($oauthPerm->isNoScope()) {
            return $this->responseWithError($response, 'invalid_request', 'No valid scope provided');
        }

        $contact_id = $request->getAttribute('contact_id');
        $event_id = $request->getAttribute('event_id');

        $code = $this->tokenGenerator->stageOAuth($client_id, $contact_id, $event_id, $oauthPerm, $expires_in, $pkce_data);
        $result = [
            'code' => $code
        ];

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
    /**
     * Standardizes OAuth JSON Error Responses
     *
     * @param ResponseInterface $response The current PSR-7 response object
     * @param string $error Standard OAuth error code (e.g., 'invalid_request', 'invalid_grant')
     * @param string $description User-friendly clue explaining the error
     * @param int $statusCode The HTTP status code to return (default 400 Bad Request)
     * @return ResponseInterface
     */
    function responseWithError(ResponseInterface $response, string $error, string $description, int $statusCode = 400): ResponseInterface
    {
        $payload = [
            'error' => $error,
            'error_description' => $description
        ];

        // Write the JSON string to the response body
        $response->getBody()->write(json_encode($payload));

        // Standard OAuth headers: application/json and disable all caching
        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}
