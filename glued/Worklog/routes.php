<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Worklog\Controllers\WorklogController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.

$app->group('/api/worklog/v1', function (RouteCollectorProxy $group) {
    $group->get ('/me', WorklogController::class . ':me_get')->setName('worklog.me.api01'); 
    $group->post('/me', WorklogController::class . ':me_post');
    $group->get ('/we', WorklogController::class . ':we_get')->setName('worklog.we.api01'); 
    $group->post('/', WorklogController::class . ':me_post')->setName('worklog.api01'); 
    $group->post('/{id}', WorklogController::class . ':patch');
});

$app->group('/worklog', function (RouteCollectorProxy $group) {
    $group->get ('/me', WorklogController::class . ':me_ui')->setName('worklog.me'); 
    $group->get ('/we', WorklogController::class . ':we_ui')->setName('worklog.we'); 
});
