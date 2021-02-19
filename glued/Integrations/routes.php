<?php
use Glued\Integrations\Controllers\IntegrationsController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/integrations/v1', function (RouteCollectorProxy $group) {
    $group->get ('/google[/{uid:[0-9]+}]', IntegrationsController::class . ':google_list')->setName('integrations.google.api01');
    $group->post ('/google[/{uid:[0-9]+}]', IntegrationsController::class . ':google_post');
    $group->patch('/google[/{uid:[0-9]+}]', IntegrationsController::class . ':google_patch');
    $group->delete('/google[/{uid:[0-9]+}]', IntegrationsController::class . ':google_delete');
    $group->post ('/google/next[/{uid:[0-9]+}]', IntegrationsController::class . ':google_progress_next')->setName('integrations.google.next.api01');
    $group->post ('/google/action[/{uid:[0-9]+}]', IntegrationsController::class . ':google_sheet_action')->setName('integrations.google.action.api01');
})->add(RestrictGuests::class);

$app->group('/integrations', function (RouteCollectorProxy $group) {
    $group->get ('/google/list', IntegrationsController::class . ':google_list_ui')->setName('integrations.google.list');
    $group->get ('/google/detail[/{uid:[0-9]+}]', IntegrationsController::class . ':google_detail_ui')->setName('integrations.google.detail');
    $group->get ('/csob', IntegrationsController::class . ':csob_get_app')->setName('intagrations.csob.app'); 
})->add(RedirectGuests::class);

