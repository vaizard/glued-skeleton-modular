<?php
use Glued\Contacts\Controllers\ContactsController;
use Glued\Covid\Controllers\CovidController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Slim\Routing\RouteCollectorProxy;


$app->get ('/covid/zakladace/import-v1', CovidController::class . ':zakladace_import_v1') -> setName('covid.zakladace.import.v1');

