<?php
declare(strict_types=1);

return [

    // Database
    'db' => [
        'host' => $_ENV['MYSQL_HOST'],
        'database' => $_ENV['MYSQL_DATABASE'],
        'username' => $_ENV['MYSQL_USERNAME'],
        'password' => $_ENV['MYSQL_PASSWORD'],
    ],

];
