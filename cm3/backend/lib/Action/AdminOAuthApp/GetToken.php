<?php

namespace CM3_Lib\Action\AdminOauthApp;

use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\util\BaseIntEncoder;
use Branca\Branca;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class GetToken 
{
    public function __construct(
        private Responder $responder,
        private oauth_app $oauthApp,
        private Branca $Branca
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $data = (array)$request->getParsedBody();
        // Fetch the OAuth app record
        $app = $this->oauthApp->GetByID($params['id']);

        if ($app === false) {
            throw new HttpNotFoundException($request);
        }

        // Validate that the app is active
        if ($app['active'] == 0) {
            throw new HttpBadRequestException($request,'OAuth App is not active');
        }

        // Encode the client_id using BaseIntEncoder
        $encodedClientId = BaseIntEncoder::encode($app['client_id']);

        // Create token payload with encoded client_id and date_token
        $dateToken = strtotime($app['date_token']);

        $result = array();
        $result['token'] = $this->Branca->encode($encodedClientId, $dateToken);

        // Build the HTTP response
        return $this->responder
             ->withJson($response, $result);
    }
}
