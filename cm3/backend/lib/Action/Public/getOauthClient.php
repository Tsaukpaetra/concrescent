<?php

namespace CM3_Lib\Action\Public;

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
class getOauthClient
{
    public function __construct(
        private Responder $responder,
        private oauth_app $oauth_app,
        private Branca $Branca
    ) {
    }
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $client_id = BaseIntEncoder::decode($params['client_id']);
        $result = $this->oauth_app->GetByID($client_id,[
            'active',
            'app_name',
            'logo_url',
            'developer_name',
            'developer_email',
            'developer_url',
            'redirect_uri'
        ]);
        if ($result === false) {
            throw new HttpNotFoundException($request,"Huh");
        }
        //Put back in the client id
        $result['client_id'] = $params['client_id'];


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
