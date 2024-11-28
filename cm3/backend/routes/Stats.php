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
    $generalPerm = $container->get(PermCheckEventPerm::class);

    $r = array(
        '/Badge' =>
        function (RouteCollectorProxy $app) use ($generalPerm) {
            $app->get('', \CM3_Lib\Action\Stats\Badge\Search::class)
            ->add($generalPerm);
            // $app->post('/export', \CM3_Lib\Action\Stats\Badge\Export::class)
            // ->add($generalPerm);
            // $app->get('/{id}', \CM3_Lib\Action\Stats\Badge\Read::class)
            // ->add($generalPerm);
        },
    );

    $app->group(
        '/Stats',
        function (RouteCollectorProxy $app) use ($r, $container) {
            //Add all the sub-routes
            foreach ($r as $route => $definition) {
                $app->group($route, $definition);
            }
            // //Special route for the Org Chart
            // $app->get('/OrgChart', \CM3_Lib\Action\Stats\OrgChart::class)
            // ->add($container->get(PermCheckEventPerm::class));
        }
    );
};
