<?php

use Slim\App;
use Slim\Routing\RouteContext;

return function (App $app) {

  //TODO: Maybe do CORS correctly instead of catch-all?
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });
    $app->add(function ($request, $handler) {
        $routingContext = RouteContext::fromRequest($request);
        $methods = $routingContext->getRoutingResults()->getAllowedMethods();
        
        // 1. Handle the dynamic origin detection (Resolves the "//Detect?" comment)
        // Grabs the exact domain making the request (e.g., your local network staging IP or production domain)
        $origin = $request->getHeaderLine('Origin');
        $allowOrigin = !empty($origin) ? $origin : '*';

        // 2. Define the allowed headers, explicitly including your new custom tracking header
        $allowedHeaders = [
            'X-Requested-With', 
            'Content-Type', 
            'Accept', 
            'Origin', 
            'Authorization',
            'X-Frontend-Base-URL'
        ];

        $extraheaders = array(
            'X-Total-Rows'
        );

        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders))
            ->withHeader('Access-Control-Allow-Methods', implode(',', $methods))
            ->withHeader('Access-Control-Expose-Headers', implode(',', $extraheaders));
    });
};
