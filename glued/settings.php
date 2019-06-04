<?php
declare(strict_types=1);
use DI\Container;
use Monolog\Logger;
use Slim\App;

return function (App $app) {
    /** @var Container $container */
    $container = $app->getContainer();
    $container->set('settings', [

        // Slim
        'displayErrorDetails' => true, // Set to false in production

        // Monolog
        'logger' => [
            'name' => 'slim-app',
            'path' =>  __DIR__ . '/../logs/app.log',
            'level' => Logger::DEBUG,
        ],

        // Database
        'db' => [
            'host' => '127.0.0.1',
            'database' => 'slim',
            'username' => 'slim',
            'password' => '*******',
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci'
        ],

        'twig' => [
            'cache' => __ROOT__ . '/private/cache',
            'auto_reload' => true,
            'debug' => false,
        ],

        // Glued globals
        'glued' => [
            'hostname' => 'glued.example.com',
            'session_def_timeout' => 7200,
            'session_min_timeout' => 300,
            'timezone' => 'Europe/Prague'
        ],
    ]);
};



