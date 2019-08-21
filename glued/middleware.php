<?php

use Nyholm\Psr7\Response as Psr7Response;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\addRoutingMiddleware;
use DI\Container;


// =================================================
// TWIG MIDDLEWARE
// ================================================= 


/*
$app->add(
    new TwigMiddleware(
        new Twig(
            __ROOT__ . '/glued/',
            $settings['twig']
        ),
        $container,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    );
);
*/

$app->add(
    new TwigMiddleware(
        $container->get('view'),
        $container,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    )
);

// =================================================
// ROUTING MIDDLEWARE
// =================================================

$app->addRoutingMiddleware();

// =================================================
// ERROR MIDDLEWARE
// =================================================

/*
 * Add Error Handling Middleware
 *
 * @param bool $displayErrorDetails -> Should be set to false in production
 * @param bool $logErrors -> Parameter is passed to the default ErrorHandler
 * @param bool $logErrorDetails -> Display error details in error log
 * which can be replaced by a callable of your choice.
 
 * NOTE: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */

$errorMiddleware = new ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    $settings['displayErrorDetails'],
    $settings['logErrors'],
    $settings['logErrorDetails']
);

// Override 404
// In classes, use: throw new HttpNotFoundException($request, 'optional message');
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception,$displayErrorDetails, $logErrors, $logErrorDetails) use ($container) {
    $response = new Psr7Response();
    return $container->get('view')->render(
        $response->withStatus(404), 
        'Core/Views/errors/404.twig'
    );
});
// TODO: add other exceptions (other then 404)
// TODO: make nice twigs
$app->add($errorMiddleware);



