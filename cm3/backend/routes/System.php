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
    $globalPerm = $container->get(PermCheckEventPerm::class)
    ->withAllowedPerm(PermEvent::GlobalAdmin());
    $staffView = $globalPerm->withAllowedPerm(PermEvent::Staff_View());

    $r = array(
        '/ErrorLog' =>
        function (RouteCollectorProxy $app) use ($globalPerm) {
            $app->get('', \CM3_Lib\Action\System\ErrorLog\Search::class)
            ->add($globalPerm);
            $app->post('/export', \CM3_Lib\Action\System\ErrorLog\Export::class)
            ->add($globalPerm);
            $app->post('', \CM3_Lib\Action\System\ErrorLog\Create::class)
            ->add($globalPerm);
            $app->get('/{id}', \CM3_Lib\Action\System\ErrorLog\Read::class)
            ->add($globalPerm);
            $app->post('/{id}', \CM3_Lib\Action\System\ErrorLog\Update::class)
            ->add($globalPerm);
        },

        '/EventInfo' =>
        function (RouteCollectorProxy $app) use ($globalPerm) {
            
            $accessPerm = $globalPerm->withAllowedPerms(array(
                PermEvent::EventAdmin()
            ));
            $app->get('', \CM3_Lib\Action\System\EventInfo\Search::class);
            $app->post('', \CM3_Lib\Action\System\EventInfo\Create::class)
            ->add($accessPerm);

            //Import and export functions
            $app->post('/import', \CM3_Lib\Action\System\EventInfo\Import::class)
                ->add($globalPerm);
            $app->post('/export', \CM3_Lib\Action\System\EventInfo\Export::class)
                ->add($globalPerm);

            $app->get('/{id}', \CM3_Lib\Action\System\EventInfo\Read::class);
            $app->post('/{id}', \CM3_Lib\Action\System\EventInfo\Update::class)
            ->add($accessPerm);
            $app->delete('/{id}', \CM3_Lib\Action\System\EventInfo\Delete::class)
            ->add($accessPerm);
        }
    );

    $app->group(
        '/System',
        function (RouteCollectorProxy $app) use ($r, $container) {
            //Add all the sub-routes
            foreach ($r as $route => $definition) {
                $app->group($route, $definition);
            }
        }
    );
};
