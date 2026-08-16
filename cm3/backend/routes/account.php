<?php

// Define app routes

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use CM3_Lib\Middleware\PermCheckOAuth;
use CM3_Lib\util\PermOAuth;

return function (App $app, $container) {
    $app->group(
        '/account',
        function (RouteCollectorProxy $app) use ($container) {
            $PermOAuth = $container->get(PermCheckOAuth::class);
            $PermOAuth->RequireScopePresence = false;
            //Default root, Gets currently logged-in user info, including admin permissions, if applicable
            $app->get('', \CM3_Lib\Action\Account\GetAccount::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_ACCOUNT
                ]));

            //Save account details
            $app->post('', \CM3_Lib\Action\Account\SetAccount::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::WRITE_ACCOUNT
                ]));

            //Save admin settings
            $app->post('/settings', \CM3_Lib\Action\Account\SetAdmin::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::WRITE_ACCOUNT
                ]));

            //Refresh the token
            $app->get('/refreshtoken', \CM3_Lib\Action\Account\RefreshToken::class)
                ->add($PermOAuth);
                
            // Create an OAuth token
            $app->post('/createoauthtoken', \CM3_Lib\Action\Account\CreateOAuthToken::class)
                ->add($PermOAuth);

            //Switch which event we're talking about
            $app->post('/switchevent', \CM3_Lib\Action\Account\SwitchEvent::class)
                ->add($PermOAuth);

            //What badges have been saved
            $app->get('/badges', \CM3_Lib\Action\Account\GetMyBadges::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_BADGES
                ]));

            //What applications have been saved
            $app->get('/applications', \CM3_Lib\Action\Account\GetMyApplications::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_BADGES
                ]));

            //Retrieve responses to forms
            $app->get('/formresponses', \CM3_Lib\Action\Account\GetMyResponses::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_BADGES
                ]));

            //Retrieve cart
            $app->get('/cart', \CM3_Lib\Action\Account\Cart\ListCarts::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_CART
                ]));

            $app->get('/cart/{id}', \CM3_Lib\Action\Account\Cart\GetCart::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::READ_CART
                ]));

            //Save cart
            $app->post('/cart[/{id}]', \CM3_Lib\Action\Account\Cart\SaveCart::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::WRITE_CART
                ]));

            //Checkout cart
            $app->post('/cart/{id}/checkout', \CM3_Lib\Action\Account\Cart\CheckoutCart::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::WRITE_CART
                ]));

            //Cancel the cart
            $app->delete('/cart/{id}', \CM3_Lib\Action\Account\Cart\CancelCart::class)
                ->add($PermOAuth->withAllowedScopes([
                    PermOAuth::WRITE_CART
                ]));

        }
    );
};
