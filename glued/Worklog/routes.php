<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Worklog\Controllers\WorklogController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.

$app->group('/api/worklog/v1', function (RouteCollectorProxy $group) {
    $group->get ('/users', WorklogController::class . ':me_get')->setName('worklog.users.api01'); 
    $group->get ('/domains', WorklogController::class . ':we_get')->setName('worklog.domains.api01'); 
    $group->post('/items[/{uid}]', WorklogController::class . ':me_post')->setName('worklog.items.api01'); 
    $group->patch('/items[/{uid}]', WorklogController::class . ':patch');
});

$app->group('/worklog', function (RouteCollectorProxy $group) {
    $group->get ('/me', WorklogController::class . ':me_ui')->setName('worklog.me'); 
    $group->get ('/we', WorklogController::class . ':we_ui')->setName('worklog.we'); 
});
