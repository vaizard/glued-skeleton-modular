<?php
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Glued\Spider\Controllers\SpiderController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/spider', function (RouteCollectorProxy $group) {
    $group->get ('/browse[/{uri}]', SpiderController::class)->setName('spider.browse.web'); 
})->add(RedirectGuests::class);
