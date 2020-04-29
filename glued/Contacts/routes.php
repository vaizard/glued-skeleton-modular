<?php
use Glued\Contacts\Controllers\ContactsController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/contacts/v1', function (RouteCollectorProxy $group) {
    $group->get ('/', ContactsController::class . ':list')->setName('contacts.items.api01'); 
    $group->post('/', ContactsController::class . ':create');
    $group->get ('/{uid:[0-9]+}', ContactsController::class . ':read');//->setName('contacts.object.api01'); 
    $group->put ('/{uid:[0-9]+}', ContactsController::class . ':update');
    $group->delete('/{uid:[0-9]+}', ContactsController::class . ':delete');
})->add(RestrictGuests::class);

$app->group('/api/contacts/search/v1', function (RouteCollectorProxy $group) {
    $group->get ('/cz-ares/names/{name}', ContactsController::class . ':cz_ares_names')->setName('contacts.search.cz-ares.names.api01'); 
    $group->get ('/cz-ares/ids/{id}', ContactsController::class . ':cz_ares_ids')->setName('contacts.search.cz-ares.ids.api01'); 
})->add(RestrictGuests::class);

$app->group('/contacts', function (RouteCollectorProxy $group) {
    $group->get ('/list', ContactsController::class . ':collection_ui')->setName('contacts.collection'); 
    $group->get ('/{uid:[0-9]+}', ContactsController::class . ':object_ui')->setName('contacts.object'); 
})->add(RedirectGuests::class);

