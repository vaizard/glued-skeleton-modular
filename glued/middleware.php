<?php

use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Nyholm\Psr7\Response as Psr7Response;


// =================================================
// ERROR MIDDLEWARE
// =================================================

$errorMiddleware = new ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    true,
    false,
    false
);

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception) use ($container) {
    $response = new Psr7Response();
    return $container->get('view')->render(
        $response->withStatus(404), 
        'Core/Views/errors/404.twig'
    );
});

// TODO: add other exceptions (other then 404)
// TODO: make nice twigs
// TODO: Add $displayErrorDetails, $logErrors, $logErrorDetails)
$app->add($errorMiddleware);


/*$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) {
  die('Not found - overriding exception httpnotfoundexception');
});*/

// =================================================
// TWIG MIDDLEWARE
// ================================================= 

$app->add(
    new TwigMiddleware(
        new Twig(
            __ROOT__ . '/glued/',
            $settings['twig']
        ),
        $container,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    )
);


/*
$app->add(
    new TwigMiddleware(
        new Twig(
            __ROOT__ . '/glued/',
            [
                'cache' => __ROOT__ . '/private/cache',
                'auto_reload' => true,
                'debug' => false,
fa            ]
        ),
        $container,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    )
);
*/


