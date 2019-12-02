<?php

use Respect\Validation\Validator as v;

// tell Respect\Validation where to look for classes
// extending its built-in rules set.
v::with('Glued\\Core\\Classes\\Validation\\Rules\\');

error_reporting(E_ALL);
ini_set('display_errors', 'true');
ini_set('display_startup_errors', 'true');
date_default_timezone_set($settings['glued']['timezone']);
