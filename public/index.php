<?php

declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;

define("__ROOT__", realpath(__DIR__ . '/..'));
include_once(__ROOT__ . '/vendor/autoload.php');

error_reporting(E_ALL);
ini_set('display_errors', 'true');
ini_set('display_startup_errors', 'true');

////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////

// Instantiate PHP-DI Container



// Instantiate settings
/*$settings = require_once __DIR__ . '/../glued/settings.php';
$settings($app);
$getsettings = $container->get('settings');


// Configure PHP
date_default_timezone_set($container->get('settings')['glued']['timezone']);
*/



// Configure PHP




require_once __DIR__ . '/../glued/container.php';

$app = AppFactory::create();

//$settings = require_once(__DIR__ . '/../glued/settings.php');
$settings = $container->get('settings');
date_default_timezone_set($settings['glued']['timezone']);


require_once __DIR__ . '/../glued/middleware.php';
require_once __DIR__ . '/../glued/routes.php';

?>
