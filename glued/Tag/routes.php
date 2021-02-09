<?php
use Glued\Tag\Controllers\TagController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/tag/v1', function (RouteCollectorProxy $group) {
    $group->get ('/doc/{tagid}[/{tagpw}]', TagController::class . ':tag_doc_get_api')->setName('tag.doc.api01'); 
    $group->get ('/create[/{count}]', TagController::class . ':tag_create_get_api')->setName('tag.create.api01'); 
})->add(RestrictGuests::class);

$app->group('/tag', function (RouteCollectorProxy $group) {
    $group->get ('/doc/{tagid}[/{tagpw}]', TagController::class . ':tag_doc_get_app')->setName('tag.doc'); 
})->add(RedirectGuests::class);

