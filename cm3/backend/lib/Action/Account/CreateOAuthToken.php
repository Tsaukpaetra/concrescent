<?php

namespace CM3_Lib\Action\Account;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use CM3_Lib\util\PermOAuth;
use CM3_Lib\util\TokenGenerator;

final class CreateOAuthToken
{
    public function __construct(private TokenGenerator $tokenGenerator, private Responder $responder)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $indata = (array)$request->getParsedBody();
        $scopes = $indata['scopes'] ?? [];
        $expires_in = $indata['expires_in'] ?? 0;
        
        if (!is_array($scopes)) {
            throw new \InvalidArgumentException('Invalid scopes array');
        }

        $validScopes = PermOAuth::toArray();

        //Ensure only valid scopes are going to be added
        $oauthPerm = array_reduce($scopes, function ($carry, $scope) use ($validScopes) {
            if (isset($validScopes[$scope])) {
                $carry->{'set'.$scope}(true);
            }
            return $carry;
        },new PermOAuth(0));
        
        if ($oauthPerm->isNoScope()) {
            throw new \InvalidArgumentException('No valid scopes provided');
        }

        $contact_id = $request->getAttribute('contact_id');
        $event_id = $request->getAttribute('event_id');
        
        $result = $this->tokenGenerator->forOAuth($contact_id, $event_id, $oauthPerm, $expires_in);
        
        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
