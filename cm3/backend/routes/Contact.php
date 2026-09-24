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
    $app->group(
        '/Contact',
        function (RouteCollectorProxy $app) use ($accessPerm) {
            $fullContactPerm = $accessPerm->withAllowedPerm(PermEvent::Contact_Full());
            $app->get('', \CM3_Lib\Action\Contact\Search::class)
            ->add($accessPerm);
            $app->post('/getbatch', \CM3_Lib\Action\Contact\GetBatch::class)
            ->add($accessPerm);
            $app->post('/getorcreatebatch', \CM3_Lib\Action\Contact\GetOrCreateBatch::class)
            ->add($accessPerm);
            $app->post('', \CM3_Lib\Action\Contact\Create::class)
            ->add($fullContactPerm);
            $app->get('/{id}', \CM3_Lib\Action\Contact\Read::class)
            ->add($fullContactPerm);
            $app->post('/{id}', \CM3_Lib\Action\Contact\Update::class)
            ->add($fullContactPerm);
            $app->delete('/{id}', \CM3_Lib\Action\Contact\Delete::class)
            ->add($accessPerm); //Only global admins can delete

            // Manage sessions.
            $app->group(
                '/{contact_id}/session',
                function (RouteCollectorProxy $app) use ($accessPerm) {
                    $app->get('', \CM3_Lib\Action\Contact\Session\Search::class)
                    ->add($accessPerm);
                    $app->get('/{token_timestamp}', \CM3_Lib\Action\Contact\Session\Read::class)
                    ->add($accessPerm);
                    $app->post('/{token_timestamp}', \CM3_Lib\Action\Contact\Session\Update::class)
                    ->add($accessPerm);
                    $app->delete('/{token_timestamp}', \CM3_Lib\Action\Contact\Session\Delete::class)
                    ->add($accessPerm);
                }
            );
        }
    );
};
