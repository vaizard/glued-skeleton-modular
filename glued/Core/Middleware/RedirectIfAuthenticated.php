<?php

namespace Glued\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Routing\RouteParser;

// TODO Consider replacing this middleware completely with RBAC / ABAC
// rules and unify authentication & authorization

class RedirectIfAuthenticated
{
    protected $routeParser;
    
    public function __construct(RouteParser $routeParser)
    {
        $this->routeParser = $routeParser;
    }
    
    public function __invoke(Request $request, Handler $handler)
    {
        $response = $handler->handle($request);
        $signedIn = true;
        if ($signedIn) {
            return $response->withHeader('Location',$this->routeParser->urlFor('web.core.dashboard') )->withStatus(302);
        }
        return $response;
    }
}


