<?php
////////////////////////////////////////////////
/// INITIALIZATION /////////////////////////////
////////////////////////////////////////////////
declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

// Set the default timezone.
date_default_timezone_set('Europe/Zurich');

// Set the absolute path to the root directory.
define(__ROOT__, realpath(__DIR__ . '/..'));

// Include the composer autoloader.
include_once(__ROOT__ . '/vendor/autoload.php');

////////////////////////////////////////////////
/// DEPENDENCIES ///////////////////////////////
////////////////////////////////////////////////

// Create the container for dependency injection.
$container = new Container();

// Set the container to create the App with AppFactory.
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add the twig middleware (which when processed would set the 'view' to the container).
$app->add(
    new TwigMiddleware(
        new Twig(
            __ROOT__ . '/glued/templates',
            [
                'cache' => __ROOT__ . '/private/cache',
                'auto_reload' => true,
                'debug' => false,
            ]
        ),
        $container,
        $app->getRouteCollector()->getRouteParser(),
        $app->getBasePath()
    )
);

////////////////////////////////////////////////
/// ROUTES /////////////////////////////////////
////////////////////////////////////////////////


foreach (glob(__ROOT__ . '/glued/*/routes.php') as $filename) {
  include_once $filename;
}


// Run the app.
$app->get('/_/phpinfo', function() { phpinfo(); })->setName('_phpinfo');

$app->get('/_/test', function() {
  echo "<h1>Quick&Dirty TEST PAGE</h1>";
  // write your code here
})->setName('_test');


$app->run();
