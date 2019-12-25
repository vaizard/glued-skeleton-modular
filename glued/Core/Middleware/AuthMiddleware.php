<?php

namespace Glued\Core\Middleware;

use Glued\Core\Middleware\AbstractMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Views\Twig;
use Psr\Container\ContainerInterface as Container;

class AuthMiddleware extends AbstractMiddleware
{

    public function __invoke(Request $request, Handler $handler)
    {

        $check = $this->auth->check();
        $response = $this->auth->response();
        $response['check'] = $check;
        // consider try/catch here

        $this->view->getEnvironment()->addGlobal('session', $_SESSION);
        $this->view->getEnvironment()->addGlobal('auth', $response);
        return $handler->handle($request);
    }
}

