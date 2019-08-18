<?php

declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;

define("__ROOT__", realpath(__DIR__ . '/..'));
require_once(__ROOT__ . '/vendor/autoload.php');

$container = new Container();
AppFactory::setContainer($container);

$container->set('settings', function() {
    return require_once(__ROOT__ . '/glued/settings.php');
});
$settings = $container->get('settings');

require_once (__ROOT__ . '/glued/bootstrap.php');
require_once (__ROOT__ . '/glued/container.php');

$app = AppFactory::create();

require_once (__ROOT__ . '/glued/middleware.php');
require_once (__ROOT__ . '/glued/routes.php');

$app->run();

?>
