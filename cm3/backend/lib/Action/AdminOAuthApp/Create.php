<?php

namespace CM3_Lib\Action\AdminOAuthApp;

use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\BaseIntEncoder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Create
{
    public function __construct(
        private Responder $responder,
        private oauth_app $oauth_app,
    ) {
    }
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();

        //Delete unnecessary things
        foreach ([
            'date_created',
            'date_modified',
            'date_token',
            'client_secret',
        ] as $value) {
            unset($data[$value]);
        }


        $result = $this->oauth_app->Create($data);
        
        $client_id = BaseIntEncoder::decode($result['id']);
        //Put back in the client id
        $result['client_id'] = $client_id;

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
