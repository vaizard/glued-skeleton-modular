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
        'path' =>  __ROOT__ . '/private/log/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],

    // Database
    'db' => [
        'host' => 'db_host',
        'database' => 'db_name',
        'username' => 'db_user',
        'password' => 'db_pass',
        'charset' => ' utf8mb4',
        'collation' => ' utf8mb4_unicode_ci'
    ],

    // Twig (set 'cache' to false to disable caching)
    'twig' => [
        'cache' => __ROOT__ . '/private/cache/twig',
        'auto_reload' => true,
        'debug' => false
    ],

    // Twig-translation
    'locale' => [
        'path' => __ROOT__ . '/private/locale',
        'cache' => __ROOT__ . '/private/cache/locale',
        'locale' => 'en_US',
        'domain' => 'messages',
    ],

    // Glued globals
    'glued' => [
        'hostname' => 'glued.example.com',
        'session_def_timeout' => 7200,
        'session_min_timeout' => 300,
        'timezone' => 'Europe/Prague'
    ],

    'assets' => [
        'path' => __ROOT__ . '/public/assets/cache',
        'url_base_path' => '/assets/cache/',
        // Cache settings
        'cache_enabled' => true,
        'cache_path' => __ROOT__ . '/private/cache',
        'cache_name' => 'assets',
        // Enable JavaScript and CSS compression
        'minify' => 1,
    ]

];
