<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Stor\Controllers\StorController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.

$app->group('/stor', function (RouteCollectorProxy $group) {
    $group->get('/browser', StorController::class . ':storBrowserGui')->setName('stor.browser');
    
    // chybne nastavene cesty, jen abychom tam meli ty jmena
    $group->get('/temp1', StorController::class . ':storBrowserGui')->setName('stor.uploader.update');
    $group->get('/temp2', StorController::class . ':storBrowserGui')->setName('stor.uploader.copy.move');
    $group->get('/temp3', StorController::class . ':storBrowserGui')->setName('stor.api.filter.options');
    
    
});
