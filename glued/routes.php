<?php

use Slim\App;

foreach (glob(__ROOT__ . '/glued/*/routes.php') as $filename) {
  include_once $filename;
}


// Run the app.
$app->get('/_/phpinfo', function() { phpinfo(); })->setName('_phpinfo');

$app->get('/_/test', function($c) {
  echo "<h1>Quick&Dirty TEST PAGE</h1>";
  echo $this->get('settings')['glued']['timezone'];
  // write your code here
})->setName('_test');


// monolog
/*
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};
*/


/*
$app->add(
    new TwigMiddleware(
        new Twig(
            __ROOT__ . '/glued/',
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
*/

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



$app->run();
