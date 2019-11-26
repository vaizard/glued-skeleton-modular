<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Spider\Controllers\SpiderController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.
$app->group('/spider', function (RouteCollectorProxy $group) {
    $group->get ('/browse[/{uri}]', SpiderController::class)->setName('spider.browse.web'); // ->add(new RedirectIfNotAuthenticated());
});
