<?php
use App\Controllers\HelloController;
use App\Controllers\HomeController;
use App\Controllers\JsonController;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/', function (RouteCollectorProxy $group) {
    $group->get('', HomeController::class)->setName('home');
    $group->get('hello/{name}', HelloController::class)->setName('hello');
    $group->get('json', JsonController::class)->setName('json');

});


