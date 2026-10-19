<?php

namespace CM3_Lib\Action\AdminOAuthApp;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\BaseIntEncoder;


use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class Search
{
    public function __construct(private Responder $responder, private oauth_app $oauth_app, private badgeinfo $badgeinfo)
    {
    }
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';
        
        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, 'client_id', defaultSortDesc:true);
        $totalRows = 0;

        $data = $this->oauth_app->Search([], order: $pg['order'], limit: $pg['limit'], offset: $pg['offset'],resultTotal: $totalRows);

        array_walk($data, function(&$client) {
            //Encode the ID to the public version
            $client['client_id'] = BaseIntEncoder::encode($client['client_id']);
        });

        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
