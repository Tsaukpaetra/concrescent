<?php

use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use CM3_Lib\Middleware\AccessLogMiddleware;
use CM3_Lib\Middleware\GZCompress;

return function (App $app, $s_config) {
    $environment = $s_config->get('environment');
    $app->setBasePath($environment['base_path']);

    /*
     * The routing middleware should be added earlier than the ErrorMiddleware
     * Otherwise exceptions thrown from it will not be handled by the middleware
     */
    $app->addRoutingMiddleware();

    //post body middleware
    $app->addBodyParsingMiddleware();

    // Gzip compression middleware
    if ($environment['use_gzip']) {
        $app->add(GZCompress::class);
    }

    //Branca token authenticator
    $app->add(new Tuupola\Middleware\BrancaAuthentication([
        "secure" => !$environment['ignore_insecure'],
        "ttl" => $environment['token_life'],
        "secret" => $environment['token_secret'],
        "ignore" =>  [
            $environment['base_path'] .'/public',
            $environment['base_path'] .'/test'
        ],
        "before" => function ($request, $arguments) use ($app) {
            //Load the CurrentUserInfo with the token data
            $CurrentUserInfo = $app->getContainer()->get(CM3_Lib\util\CurrentUserInfo::class);
            $CurrentUserInfo->fromToken($arguments['decoded']);
            //Get the expiration
            $branca = $app->getContainer()->get(Branca\Branca::class);
            $token_timestamp = $branca->timestamp($arguments['token']);

            //Check if the session info is still valid
            $contactsessionTable = $app->getContainer()->get(CM3_Lib\models\contact_session::class);
            $sessionData = $contactsessionTable->GetByID(['contact_id' => $CurrentUserInfo->GetContactId(),
            'token_timestamp' => $token_timestamp]);
            if($sessionData === false) {
                //Session revoked, go away
                throw new Slim\Exception\HttpUnauthorizedException($request, 'Token Revoked');
            }

            // TODO: Maybe check that the session wasn't hijacked?

            //Throw the result in as attributes
            return $request
              ->withAttribute("session", $sessionData)
              ->withAttribute("contact_id", $CurrentUserInfo->GetContactId())
              ->withAttribute("event_id", $CurrentUserInfo->GetEventId())
              ->withAttribute("perms", $CurrentUserInfo->GetPerms())
              ->withAttribute("userscopes", $CurrentUserInfo->GetOAuthPerms());
        },
        "error" => function ($request, $response, $arguments) {
            $data['error']["message"] = $arguments["message"];
            $response->getBody()->write(json_encode($data));
        
            return $response
                ->withStatus(401)
                ->withHeader("Content-Type", "application/json");
        }
    ]));
    //Add the authorization-as-query parameter
    $app->add(new CM3_Lib\Middleware\authInGet());

    $app->add(ErrorMiddleware::class);
    $app->add(AccessLogMiddleware::class);
};
