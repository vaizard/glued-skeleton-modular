<?php
use Glued\Store\Controllers\StoreController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/store/v1', function (RouteCollectorProxy $group) {
    $group->get ('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_get_api')->setName('store.sellers.api01'); 
    $group->post('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_post_api');
    $group->patch('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_patch_api');
    $group->delete('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_delete_api');
    $group->get ('/items[/{uid:[0-9]+}]', StoreController::class . ':items_get_api')->setName('store.items.api01'); 
    $group->post('/items[/{uid:[0-9]+}]', StoreController::class . ':items_post_api');
    $group->patch('/items[/{uid:[0-9]+}]', StoreController::class . ':items_patch_api');
    $group->delete('/items[/{uid:[0-9]+}]', StoreController::class . ':items_delete_api');
})->add(RestrictGuests::class);

$app->group('/store', function (RouteCollectorProxy $group) {
    $group->get ('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_get_app')->setName('store.sellers'); 
    $group->get ('/items', StoreController::class . ':items_get_app')->setName('store.items'); 
    $group->get ('/subscritpions', StoreController::class . ':subscriptions_get_app')->setName('store.subscriptions'); 
    $group->get ('/tickets', StoreController::class . ':tickets_get_app')->setName('store.tickets'); 
})->add(RedirectGuests::class);

