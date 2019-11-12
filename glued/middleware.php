<?php

use DI\Container;
use Middlewares\TrailingSlash;
use Nyholm\Psr7\Response as Psr7Response;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\addRoutingMiddleware;
use Glued\Core\Middleware\LocaleSessionMiddleware; // Twig-translation
use Glued\Core\Middleware\TranslatorMiddleware; // Twig-translation
use Glued\Core\Middleware\Timer;


// =================================================
// EXAMPLE (BEFORE) MIDDLEWARE
// ================================================= 

// The example below implemented as a closure shows
// a authentication middleware stub. For a production
// equivalent see Core/Middleware/RedirectIfNotAuthenticated.php
// implemented as a class.

/*
$beforeMiddleware = function ($request, $handler) {
    $response = $handler->handle($request);
    $signedIn = false;
    if (!$signedIn) {
        die('You are not signed in');
    }
    return $response;
};
$app->add($beforeMiddleware);
*/

// =================================================
// TWIG MIDDLEWARE
// ================================================= 

$app->add(TwigMiddleware::createFromContainer($app));

// =================================================
// TWIG TRANSLATION MIDDLEWARE
// ================================================= 

$app->add(TranslatorMiddleware::class); // Twig-translation
$app->add(LocaleSessionMiddleware::class); // Twig-translation

// =================================================
// TIMER MIDDLEWARE
// ================================================= 

$app->add(Timer::class); // Twig-translation


// =================================================
// ROUTING MIDDLEWARE
// =================================================

$app->addRoutingMiddleware();


// =================================================
// TRAILING SLASH MIDDLEWARE
// =================================================

/**
 * Add Trailing Slash middleware.
 * TrailingSlash(false) means trailing slash is disabled (i.e. https://example.com/user)
 * redirect(true) enforces a 301 redirect from https://example.com/user/ to https://example.com/user
 */
$trailingSlash = new TrailingSlash(false);
$trailingSlash->redirect(true);
$app->add($trailingSlash);

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
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($container) {
    $response = new Psr7Response();
    return $container->get('view')->render(
        $response->withStatus(404), 
        'Core/Views/errors/404.twig'
    );
});

// TODO: add other exceptions (other then 404)
// TODO: make nice twigs
$app->add($errorMiddleware);



