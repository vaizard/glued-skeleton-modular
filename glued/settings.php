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
        'debug' => false,
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
        'url_base_path' => 'cache/',
        // Cache settings
        'cache_enabled' => true,
        'cache_path' => __ROOT__ . '/private/tmp',
        'cache_name' => 'assets-cache',
        // Enable JavaScript and CSS compression
        'minify' => 1,
    ]
/*
$settings['root']                       __ROOT__
$settings['temp']                       __ROOT__ . '/tmp'
$settings['public']                     __ROOT__ . '/public'
$settings['twig']['path']               __ROOT__ . '/templates',
$settings['twig']['cache_path']         $settings['temp'] . '/twig-cache',
$settings['assets']['path']             $settings['public'] . '/cache',
$settings['assets']['url_base_path']    'cache/',
$settings['assets']['cache_enabled']    true,
$settings['assets']['cache_path']       $settings['temp'],
$settings['assets']['cache_name']       'assets-cache',
$settings['assets']['minify']           1



$settings['root'] = dirname(__DIR__);
$settings['temp'] = $settings['root'] . '/tmp';
$settings['public'] = $settings['root'] . '/public';
// Error Handling Middleware settings
$settings['error_handler_middleware'] = [
    // Should be set to false in production
    'display_error_details' => true,
    // Parameter is passed to the default ErrorHandler
    // View in rendered output by enabling the "displayErrorDetails" setting.
    // For the console and unit tests we also disable
    'log_errors' => PHP_SAPI !== 'cli',
    // Display error details in error log
    'log_error_details' => true,
];
// Application settings
$settings['app'] = [
    'secret' => '{{app_secret}}',
];
// Logger settings
$settings['logger'] = [
    'name' => 'app',
    'path' => $settings['temp'] . '/logs',
    'filename' => 'app.log',
    'level' => \Monolog\Logger::ERROR,
    'file_permission' => 0775,
];
// View settings
$settings['twig'] = [
    'path' => $settings['root'] . '/templates',
    'cache_path' => $settings['temp'] . '/twig-cache',
];
// Assets
$settings['assets'] = [
    // Public assets cache directory
    'path' => $settings['public'] . '/cache',
    'url_base_path' => 'cache/',
    // Cache settings
    'cache_enabled' => true,
    'cache_path' => $settings['temp'],
    'cache_name' => 'assets-cache',
    // Enable JavaScript and CSS compression
    'minify' => 1,
];
*/

];
