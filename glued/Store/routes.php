<?php
use Glued\Store\Controllers\StoreController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/store/v1', function (RouteCollectorProxy $group) {
    $group->get ('/accounts[/{uid:[0-9]+}]', StoreController::class . ':sellers_list')->setName('store.sellers.api01'); 
    $group->post('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_post');
    $group->patch('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_patch');
    $group->delete('/sellers[/{uid:[0-9]+}]', StoreController::class . ':sellers_delete');
    $group->get ('/sellers/sync[/{uid:[0-9]+}[/{from:[12]\d{3}\-\d{2}\-\d{2}}[/{to:[12]\d{3}\-\d{2}\-\d{2}}]]]', StoreController::class . ':sellers_sync')->setName('store.accounts.sync.api01');
    $group->get ('/trx[/{uid:[0-9]+}]', StoreController::class . ':trx_list')->setName('store.trx.api01'); 
    $group->post ('/trx[/{uid:[0-9]+}]', StoreController::class . ':trx_post');
    $group->patch('/trx[/{uid:[0-9]+}]', StoreController::class . ':trx_patch');
    $group->delete('/trx[/{uid:[0-9]+}]', StoreController::class . ':trx_delete');
    $group->get ('/costs[/{uid:[0-9]+}]', StoreController::class . ':costs_list')->setName('store.costs.api01'); 
    $group->post ('/costs[/{uid:[0-9]+}]', StoreController::class . ':costs_post');
    $group->patch('/costs[/{uid:[0-9]+}]', StoreController::class . ':costs_patch');
    $group->delete('/costs[/{uid:[0-9]+}]', StoreController::class . ':costs_delete');
})->add(RestrictGuests::class);

$app->group('/store', function (RouteCollectorProxy $group) {
    $group->get ('/sellers', StoreController::class . ':sellers_list_ui')->setName('store.sellers'); 
    $group->get ('/items', StoreController::class . ':items_list_ui')->setName('store.items'); 
    $group->get ('/subscritpions', StoreController::class . ':subscriptions_list_ui')->setName('store.subscriptions'); 
    $group->get ('/tickets', StoreController::class . ':tickets_list_ui')->setName('store.tickets'); 
})->add(RedirectGuests::class);

