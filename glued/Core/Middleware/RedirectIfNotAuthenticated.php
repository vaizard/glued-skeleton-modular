<?php

namespace Glued\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

// TODO Consider replacing this middleware completely with RBAC / ABAC
// rules and unify authentication & authorization

class RedirectIfNotAuthenticated
{

    public function __invoke(Request $request, Handler $handler)
    {
        $response = $handler->handle($request);
        $signedIn = false;
        if (!$signedIn) {
            return $response->withHeader('Location','/signin')->withStatus(302);
        }
        return $response;
    }
}