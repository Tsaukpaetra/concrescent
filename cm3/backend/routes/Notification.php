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
    $generalPerm = $container->get(PermCheckEventPerm::class)->withAllowedPerm(PermEvent::EventAdmin());

    $m = array(
        '/Template' =>
        function (RouteCollectorProxy $app) use ($generalPerm) {
            $app->get('/{context}', \CM3_Lib\Action\Notification\Mail\Template\Search::class)
            ->add($generalPerm);
            // $app->post('/export', \CM3_Lib\Action\Notification\Mail\Template\Export::class)
            // ->add($generalPerm);
            $app->get('/{context}/{name}', \CM3_Lib\Action\Notification\Mail\Template\Read::class)
            ->add($generalPerm);
        },
    );

    $app->group(
        '/Mail',
        function (RouteCollectorProxy $app) use ($m, $container) {
            //Add all the sub-routes
            foreach ($m as $route => $definition) {
                $app->group($route, $definition);
            }
            // //Special route for the Org Chart
            // $app->get('/OrgChart', \CM3_Lib\Action\Stats\OrgChart::class)
            // ->add($container->get(PermCheckEventPerm::class));
        }
    );
};
