<?php

namespace CM3_Lib\Middleware;

use CM3_Lib\util\PermOAuth;
use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class PermCheckOAuth
{
    public array $AllowedScopes = array();
    /**
     * If set to true, an exception will be thrown if no scopes are present (or insufficient scopes are present).
     * If set to false, the middleware will automatically pass if and only if there are no scopes in the request
     */
    public bool $RequireScopePresence = false;
    public function __construct()
    {
    }
    public function __invoke(Request $request, RequestHandler $handler): \Nyholm\Psr7\Response
    {
        $scopes = $request->getAttribute('userscopes');
        $hasScope = false;

        //Short circuit if there are no scopes
        if (is_null($scopes) || $scopes->isNoScope()) {
            if($this->RequireScopePresence)
                throw new HttpUnauthorizedException($request, 'Not accessible without any scopes');
            else 
                //No scopes present, so pass it along since they're not checked if not present
                return $handler->handle($request);
        }

        foreach ($this->AllowedScopes as $value) {
            if ($value instanceof PermOAuth) {
                $hasScope |= $scopes->getValue() & $value->getValue();
            }
        }
        //Check if we don't have any required scopes
        if (count($this->AllowedScopes) == 0) {
            $hasScope = $scopes->getValue() > 0;
        }
        if (!$hasScope) {
            throw new HttpUnauthorizedException($request, 'Not accessible with current scopes. Need one of: ' . implode(',',$this->getCollapsedAllowedScopes()->getKey()));
        }
        return  $handler->handle($request);
    }
    public function withAllowedScopes(array $scopes)
    {
        $new = clone $this;
        $new->AllowedScopes = $scopes;
        return $new;
    }
    public function withAllowedScope(PermOAuth $scopeToAdd)
    {
        $new = clone $this;
        $new->AllowedScopes[] = $scopeToAdd;
        return $new;
    }
    public function getCollapsedAllowedScopes() : PermOAuth
    {
        $collapsed = 0;
        foreach ($this->AllowedScopes as $scope) {
            $collapsed |= $scope;
        }
        return new PermOAuth($collapsed);

    }
}
