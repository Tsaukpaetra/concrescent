<?php

// Define app routes
use CM3_Lib\util\PermEvent;
use CM3_Lib\Middleware\PermCheckEventPerm;

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return function (App $app, $container) {
    $accessPerm = $container->get(PermCheckEventPerm::class);
    $app->get('/AdminOAuthApp/GetPerms', \CM3_Lib\Action\AdminOAuthApp\GetPerms::class);
    $app->group(
        '/AdminOAuthApp',
        function (RouteCollectorProxy $app) {
            $app->get('', \CM3_Lib\Action\AdminOAuthApp\Search::class);
            $app->post('', \CM3_Lib\Action\AdminOAuthApp\Create::class);
            $app->get('/{id}', \CM3_Lib\Action\AdminOAuthApp\Read::class);
            $app->post('/{id}', \CM3_Lib\Action\AdminOAuthApp\Update::class);
            $app->delete('/{id}', \CM3_Lib\Action\AdminOAuthApp\Delete::class);
        }
    )->add($accessPerm->withAllowedPerms(array(PermEvent::GlobalAdmin)));
};
