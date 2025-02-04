<?php

namespace CM3_Lib\Middleware;

use CM3_Lib\util\PermEvent;
use CM3_Lib\util\PermGroup;
use CM3_Lib\util\EventPermissions;

use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpInternalServerErrorException;

class authInGet
{
    public array $AllowedPerms = array();
    public ?string $AttributeName = null;
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct()
    {
    }
    /**
     * Example middleware invokable class
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): \Nyholm\Psr7\Response
    {
        $auth = $request->getQueryParams()['auth'] ??'';
        
        if (!empty($auth)) {
            //Throw it into the Authorization header and assume it's a bearer token
            return  $handler->handle($request->withHeader('Authorization','Bearer '.$auth));
        }


        return  $handler->handle($request);
    }

}
