<?php

// Define app routes
use CM3_Lib\util\PermEvent;
use CM3_Lib\util\PermGroup;
use CM3_Lib\Middleware\PermCheckGroupByContextCode;

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return function (App $app, $container) {
    $generalPerm = $container->get(PermCheckGroupByContextCode::class)->withAttributeName('context');

    $m = array(
        '/Template' =>
        function (RouteCollectorProxy $app) use ($generalPerm) {
            $gpEdit = $generalPerm->withAllowedPerm(PermGroup::Badge_Manage());
            $app->get('/{context}', \CM3_Lib\Action\Notification\Mail\Template\Search::class)
            ->add($generalPerm);
            // $app->post('/export', \CM3_Lib\Action\Notification\Mail\Template\Export::class)
            // ->add($generalPerm);
            $app->get('/{context}/{name}', \CM3_Lib\Action\Notification\Mail\Template\Read::class)
            ->add($generalPerm);
            $app->put('/{context}/{name}', \CM3_Lib\Action\Notification\Mail\Template\Update::class)
            ->add($gpEdit);
            $app->patch('/{context}/{name}', \CM3_Lib\Action\Notification\Mail\Template\Render::class)
            ->add($generalPerm);
            $app->delete('/{context}/{name}', \CM3_Lib\Action\Notification\Mail\Template\Delete::class)
            ->add($gpEdit);
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
