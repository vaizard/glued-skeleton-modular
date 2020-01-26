<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Stor\Controllers\StorController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.

$app->group('/stor', function (RouteCollectorProxy $group) {
    $group->get('/browser', StorController::class . ':browser')->setName('stor.browser');
});
