<?php

namespace CM3_Lib\Action\AdminOAuthApp;

use CM3_Lib\classes\response;
use CM3_Lib\database\SearchDefinition;
use CM3_Lib\database\SearchTerm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Delete
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Implement logic to delete an OAuthApp
        $searchDef = new SearchDefinition();
        $searchDef->addSearchTerm(new SearchTerm('client_id', $args['id']));
        $result = \CM3_Lib\Models\Admin\OAuthApp::search($searchDef);
        if ($result) {
            $result = \CM3_Lib\Models\Admin\OAuthApp::delete($args['id']);
        }

        return response::json($result, $response);
    }
}
