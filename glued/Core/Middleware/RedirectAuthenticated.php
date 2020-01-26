<?php

namespace Glued\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Routing\RouteParser;


/**
 * Restricts authenticated users from accessing pages only
 * intedned for guests by application design (signup, signin, etc.).
 */

class RedirectAuthenticated extends AbstractMiddleware
{

    public function __invoke(Request $request, Handler $handler)
    {
        $response = $handler->handle($request);
        if ($this->auth->check()) {
            $this->flash->addMessage('info', __('You can\'t do this right now unless you log out. Going back to the dashboard.'));
            return $response->withHeader('Location',$this->routerParser->urlFor('core.dashboard.web') )->withStatus(302);
        }
        return $response;
    }
}


