<?php

namespace CM3_Lib\Action\AdminOAuthApp;

use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\util\badgeinfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\Responder\Responder;
use CM3_Lib\util\BaseIntEncoder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Branca\Branca;
use Slim\Exception\HttpNotFoundException;
class Read
{
    public function __construct(
        private Responder $responder,
        private oauth_app $oauth_app,
        private Branca $Branca
    ) {
    }
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $client_id = BaseIntEncoder::decode($params['id']);
        $result = $this->oauth_app->GetByID($client_id,'*');
        if ($result === false) {
            throw new HttpNotFoundException($request);
        }
        //Put back in the client id
        $result['client_id'] = $params['id'];

        //Generate the token
        $result['client_secret'] = $this->Branca->encode($params['id'], strtotime($result['date_token']));

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
