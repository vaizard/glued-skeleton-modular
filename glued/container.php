<?php

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

$container = new Container();
AppFactory::setContainer($container);

$container->set(LoggerInterface::class, function (Container $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Logger($settings['name']);
    $processor = new UidProcessor();
    $logger->pushProcessor($processor);
    $handler = new StreamHandler($settings['path'], $settings['level']);
    $logger->pushHandler($handler);
    return $logger;
});

$container->set('mysqli', function (Container $c) {
    $db = $c->get('settings')['db'];
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = ".$db['collation']);
    return $mysqli;
});

$container->set('settings', function() { 
    return (
    [
        // Slim
        'displayErrorDetails' => true, // Set to false in production

        // Monolog
        'logger' => [
            'name' => 'glued',
            'path' =>  __DIR__ . '/../logs/app.log',
            'level' => Logger::DEBUG,
        ],

        // Database
        'db' => [
            'host' => '127.0.0.1',
            'database' => 'glued',
            'username' => 'glued',
            'password' => 'glued-pw',
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
});

/*
$container->set('mysqli', function ($c) {
    $db = $c->get('settings')['db'];
    echo "mysqli";
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = ".$db['collation']);
    return new $mysqli($mysqli);
});

$container->set('db', function () {
    $mysqli = $container->get('mysqli');
    $db = new \MysqliDb ($mysqli);
    return $db;
});


$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('logger', function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
});

*/

/*
PIMPLE VERSION

$container['mysqli'] = function ($container) {
    $db = $container['settings']['db'];
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = ".$db['collation']);
    return $mysqli;
};


// database (joshcam/PHP-MySQLi-Database-Class)
$container['db'] = function ($container) {
    $mysqli = $container->get('mysqli');
    $db = new \MysqliDb ($mysqli);
    return $db;
};


// flash messages
$container['flash'] = function ($container) {
    return new \Slim\Flash\Messages();
};


// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};


*/