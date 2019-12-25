<?php

use DI\Container;
use Glued\Core\Classes\Error\HtmlErrorRenderer;
use Glued\Core\Middleware\HeadersMiddleware;
use Glued\Core\Middleware\LocaleSessionMiddleware;
use Glued\Core\Middleware\SessionMiddleware;
use Glued\Core\Middleware\Timer;
use Glued\Core\Middleware\TranslatorMiddleware;
use Glued\Core\Middleware\TwigCspMiddleware;
use Glued\Core\Middleware\TwigFlashMiddleware;
use Middlewares\Csp;
use Middlewares\TrailingSlash;
use Nyholm\Psr7\Response as Psr7Response;
use ParagonIE\CSPBuilder\CSPBuilder;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\addRoutingMiddleware;
use Tuupola\Middleware\CorsMiddleware;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware;



$app->add(TwigMiddleware::createFromContainer($app));
$app->add(TranslatorMiddleware::class);
$app->add(LocaleSessionMiddleware::class);
$app->add(Timer::class); // adds time needed to generate a response to headers
$app->addRoutingMiddleware();


/**
 * *******************************
 * TRAILING SLASH MIDDLEWARE
 * *******************************
 * 
 * TrailingSlash(false) means trailing slash is disabled (i.e. https://example.com/user)
 * redirect(true) enforces a 301 redirect from https://example.com/user/ to https://example.com/user
 */
$trailingSlash = new TrailingSlash(false);
$trailingSlash->redirect(true);
$app->add($trailingSlash);


$app->add(\Glued\Core\Middleware\ValidationFormsMiddleware::class);

// TODO: consider joining the TwigFlashMilldeware, TwigCSPMilldeware
// and AuthMiddleware into a single middleware TwigGlobalsMiddleware.
// We're only setting twig globals in all three so, why not, eh?
$app->add(new \Glued\Core\Middleware\TwigFlashMiddleware($container)); 
$csp = new CSPBuilder($settings['headers']['csp']);
// TODO: look at how to make this work with RJSF and its inlined evals and scripts
// then kill the dummy nonce and keep on going with $csp->nonce() only
// $nonce['script_src'] = $csp->nonce('script-src');
// $nonce['style_src'] = $csp->nonce('style-src');
$nonce['script_src'] = "dummy_nonce"; 
$app->add(new Middlewares\Csp($csp));
$app->add(new \Glued\Core\Middleware\TwigCspMiddleware($nonce, $container));

$app->add(new Tuupola\Middleware\CorsMiddleware);
// TODO add sane defaults to CorsMiddleware

$headersMiddleware = new HeadersMiddleware($settings);
$app->add($headersMiddleware);


$app->add(new \Glued\Core\Middleware\AuthMiddleware($container));

$sessionMiddleware = new SessionMiddleware($settings);
$app->add($sessionMiddleware);




/**
 * *******************************
 * ERROR HANDLING MIDDLEWARE
 * *******************************
 * 
 * This middleware must be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */

if ($settings['debugEngine'] == "Whoops") {

    $app->add(new Zeuxisoo\Whoops\Slim\WhoopsMiddleware([
        'enable' => true,
        'editor' => 'sublime',
        'title'  => 'Custom whoops page title',
    ]));

} else {

    /**
     * @param bool $displayErrorDetails -> Should be set to false in production
     * @param bool $logErrors -> Parameter is passed to the default ErrorHandler
     * @param bool $logErrorDetails -> Display error details in error log
     * which can be replaced by a callable of your choice.
     */
    $errorMiddleware = new ErrorMiddleware(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $settings['displayErrorDetails'],
        $settings['logErrors'],
        $settings['logErrorDetails']
    );

    if ($settings['displayErrorDetails'] === false) {
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        $errorHandler->registerErrorRenderer('text/html', HtmlErrorRenderer::class);
        // TODO beautify html renderer
        // TODO review json renderer & modify if needed
        // TODO 
        // HELP https://akrabat.com/custom-error-rendering-in-slim-4/
    }

    $app->add($errorMiddleware);

    /*
    // Example 404 override. Usage: `throw new HttpNotFoundException($request, 'optional message');`
    // Must be placed above the $app->add();
    $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($container) {
        $response = new Psr7Response();
        return $container->get('view')->render(
            $response->withStatus(404), 
            'Core/Views/errors/404.twig'
        );
    });
    */
   
}







