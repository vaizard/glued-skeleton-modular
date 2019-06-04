<?php

declare(strict_types=1);


use DI\Container;
use Slim\Factory\AppFactory;


// Set the absolute path to the root directory.
define("__ROOT__", realpath(__DIR__ . '/..'));

// Include the composer autoloader.
include_once(__ROOT__ . '/vendor/autoload.php');

// Instantiate PHP-DI Container
$container = new Container();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Instantiate settings
$settings = require_once __DIR__ . '/../glued/settings.php';
$settings($app);
$getsettings = $container->get('settings');


// Configure PHP
date_default_timezone_set($container->get('settings')['glued']['timezone']);


require_once __DIR__ . '/../glued/middleware.php';
require_once __DIR__ . '/../glued/routes.php';

?>
