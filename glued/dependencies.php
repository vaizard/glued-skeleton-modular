<?php

$container->set('mysqli', function () {
    $db = $this->get('settings')['db'];
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = ".$db['collation']);
    return new Mysqli($mysqli);
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