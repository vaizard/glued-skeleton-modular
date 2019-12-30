<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Worklog\Controllers\WorklogController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.
$app->group('/worklog', function (RouteCollectorProxy $group) {
    $group->get ('/i', WorklogController::class)->setName('worklog.i.web'); 
    $group->get ('/we', WorklogController::class)->setName('worklog.we.web'); 
});
