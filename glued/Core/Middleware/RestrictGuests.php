<?php

namespace Glued\Core\Middleware;

use Glued\Core\Classes\Json\JsonResponseBuilder;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Http\Factory\DecoratedResponseFactory;

/**
 * Restricts unauthenticated api requests when clients try to 
 * access endpoints intedned only for authenticated users.
 */
class RestrictGuests extends AbstractMiddleware
{

    public function __invoke(Request $request, Handler $handler)
    {
        $nyholmFactory = new Psr17Factory();
        $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
        $jwt_attr = $this->settings['auth']['jwt']['attribute'];
        $ses_attr = $this->settings['auth']['session']['attribute'];

        if ( (!$this->auth->check()) and (!$this->auth->check_jwt($request)) ) {
            $builder = new JsonResponseBuilder('core/auth', 1);
            $payload = $builder->withMessage('You must be signed in to be able to do this.')
                               ->withCode(403)
                               ->build();
            $response = $decoratedResponseFactory->createResponse(403, 'Unauthorized')->withJson($payload);
            return $response;
        }
        return $handler->handle($request);
    }
}
