<?php

use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$ss = $container->get('settings');

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


