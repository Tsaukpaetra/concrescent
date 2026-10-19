<?php

namespace CM3_Lib\Action\AdminOAuthApp;

use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\BaseIntEncoder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Update
{
    public function __construct(
        private Responder $responder,
        private oauth_app $oauth_app,
    ) {
    }
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        $client_id = BaseIntEncoder::decode($params['id']);
        //Put back in the internal client id
        $data['client_id'] = $client_id;
        $result = $this->oauth_app->Update($data);
        
        //Put back in the public client id
        $result['client_id'] = $params['id'];

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
