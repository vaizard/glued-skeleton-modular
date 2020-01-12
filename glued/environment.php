<?php
declare(strict_types=1);

use Respect\Validation\Validator as v;

// tell Respect\Validation where to look for classes extending its built-in rules set.
v::with('Glued\\Core\\Classes\\Validation\\Rules\\');
$settings = $container->get('settings');

error_reporting(E_ALL);
ini_set('display_errors', $settings['displayErrorDetails'] ? 'true' : 'false');
ini_set('display_startup_errors', $settings['displayErrorDetails'] ? 'true' : 'false');
date_default_timezone_set($settings['glued']['timezone']);

