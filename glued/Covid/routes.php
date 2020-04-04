<?php
use Glued\Contacts\Controllers\ContactsController;
use Glued\Core\Middleware\AntiXSSMiddleware;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Glued\Covid\Controllers\CovidController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Slim\Routing\RouteCollectorProxy;


$app->get ('/covid/zakladace/import-v1', CovidController::class . ':zakladace_import_v1') -> setName('covid.zakladace.import.v1')->add(AntiXSSMiddleware::class);
$app->get ('/covid/zakladace/stav[/{email}]', CovidController::class . ':zakladace_stav') -> setName('covid.zakladace.stav')->add(AntiXSSMiddleware::class);
$app->post ('/covid/zakladace/adresa', CovidController::class . ':zakladace_adr') -> setName('covid.zakladace.adresa')->add(AntiXSSMiddleware::class);
$app->get ('/covid/zakladace/send-emails', CovidController::class . ':zakladace_email') -> setName('covid.zakladace.send-emails')->add(AntiXSSMiddleware::class);

