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
    $staffPerm = $container->get(PermCheckEventPerm::class)
    ->withAllowedPerm(PermEvent::Staff_Manage());
    $staffView = $staffPerm->withAllowedPerm(PermEvent::Staff_View());

    $r = array(
        '/Badge' =>
        function (RouteCollectorProxy $app) use ($staffPerm) {
            $app->get('', \CM3_Lib\Action\Staff\Badge\Search::class)
            ->add($staffPerm->withAllowedPerms(array(
                PermEvent::Staff_View()
            )));
            $app->post('/export', \CM3_Lib\Action\Staff\Badge\Export::class)
            ->add($staffPerm->withAllowedPerms(array(
                PermEvent::Staff_Export()
            )));
            $app->post('', \CM3_Lib\Action\Staff\Badge\Create::class)
            ->add($staffPerm->withAllowedPerms(array(
                PermEvent::Staff_Edit()
            )));
            $app->get('/{id}', \CM3_Lib\Action\Staff\Badge\Read::class)
            ->add($staffPerm->withAllowedPerms(array(
                PermEvent::Staff_View(),
                PermEvent::Staff_Edit()
            )));
            $app->post('/{id}', \CM3_Lib\Action\Staff\Badge\Update::class)
            ->add($staffPerm->withAllowedPerms(array(
                PermEvent::Staff_View(),
                PermEvent::Staff_Edit()
            )));
        },
        '/BadgeType' => function (RouteCollectorProxy $app) use ($staffPerm, $staffView) {
            $app->get('', \CM3_Lib\Action\Staff\BadgeType\Search::class)
            ->add($staffView);
            $app->post('', \CM3_Lib\Action\Staff\BadgeType\Create::class)
            ->add($staffPerm);
            $app->get('/{id}', \CM3_Lib\Action\Staff\BadgeType\Read::class)
            ->add($staffView);
            $app->post('/{id}', \CM3_Lib\Action\Staff\BadgeType\Update::class)
            ->add($staffPerm);
            $app->post('/{id}/Move', \CM3_Lib\Action\Staff\BadgeType\Move::class)
            ->add($staffPerm);
            $app->delete('/{id}', \CM3_Lib\Action\Staff\BadgeType\Delete::class)
            ->add($staffPerm);
        },
        '/Department' => function (RouteCollectorProxy $app) use ($staffPerm, $staffView) {
            $app->get('', \CM3_Lib\Action\Staff\Department\Search::class)
            ->add($staffView);
            $app->post('', \CM3_Lib\Action\Staff\Department\Create::class)
            ->add($staffPerm);
            $app->get('/{id}', \CM3_Lib\Action\Staff\Department\Read::class)
            ->add($staffView);
            $app->post('/{id}', \CM3_Lib\Action\Staff\Department\Update::class)
            ->add($staffPerm);
            $app->post('/{id}/Move', \CM3_Lib\Action\Staff\Department\Move::class)
            ->add($staffPerm);
            $app->delete('/{id}', \CM3_Lib\Action\Staff\Department\Delete::class)
            ->add($staffPerm);
            $app->group('/{department_id}/Position', function (RouteCollectorProxy $app) use ($staffPerm, $staffView) {
                $app->get('', \CM3_Lib\Action\Staff\Position\Search::class)
                ->add($staffView);
                $app->post('', \CM3_Lib\Action\Staff\Position\Create::class)
                ->add($staffPerm);
                $app->get('/{id}', \CM3_Lib\Action\Staff\Position\Read::class)
                ->add($staffView);
                $app->post('/{id}', \CM3_Lib\Action\Staff\Position\Update::class)
                ->add($staffPerm);
                $app->delete('/{id}', \CM3_Lib\Action\Staff\Position\Delete::class)
                ->add($staffPerm);
            });
        },
    );

    $app->group(
        '/Staff',
        function (RouteCollectorProxy $app) use ($r, $container) {
            //Add all the sub-routes
            foreach ($r as $route => $definition) {
                $app->group($route, $definition);
            }
            //Special route for the Org Chart
            $app->get('/OrgChart', \CM3_Lib\Action\Staff\OrgChart::class)
            ->add($container->get(PermCheckEventPerm::class));
        }
    );
};
