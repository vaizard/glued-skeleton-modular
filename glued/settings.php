<?php
declare(strict_types=1);

return [

    // Slim
    'displayErrorDetails' => true, // Set to false in production
    'logErrors' => true,
    'logErrorDetails' => true,

    // Monolog
    'logger' => [
        'name' => 'glued',
        'path' =>  __DIR__ . '/../logs/app.log',
        'level' => \Monolog\Logger::DEBUG,
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
        'cache' => __ROOT__ . '/private/cache/twig',
        'auto_reload' => true,
        'debug' => false
    ],

    // Glued globals
    'glued' => [
        'hostname' => 'glued.example.com',
        'session_def_timeout' => 7200,
        'session_min_timeout' => 300,
        'timezone' => 'Europe/Prague'
    ],

    'assets' => [
        'path' => __ROOT__ . '/public/cache',
        'url_base_path' => '/cache/',
        // Cache settings
        'cache_enabled' => true,
        'cache_path' => __ROOT__ . '/private/tmp',
        'cache_name' => 'assets-cache',
        // Enable JavaScript and CSS compression
        'minify' => 1,
    ]

];
