<?php
use Glued\Enterprise\Controllers\EnterpriseController;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/api/enterprise/v1', function (RouteCollectorProxy $group) {
    $group->get ('/projects[/{uid:[0-9]+}]', EnterpriseController::class . ':projects_list')->setName('enterprise.projects.api01'); 
    $group->post('/projects[/{uid:[0-9]+}]', EnterpriseController::class . ':projects_post');
    $group->patch('/projects[/{uid:[0-9]+}]', EnterpriseController::class . ':projects_patch');
    $group->delete('/projects[/{uid:[0-9]+}]', EnterpriseController::class . ':projects_delete');
    $group->get ('/opportunities[/{uid:[0-9]+}]', EnterpriseController::class . ':opportunities_list')->setName('enterprise.opportunities.api01'); 
    $group->post ('/opportunities[/{uid:[0-9]+}]', EnterpriseController::class . ':opportunities_post');
    $group->patch('/opportunities[/{uid:[0-9]+}]', EnterpriseController::class . ':opportunities_patch');
    $group->delete('/opportunities[/{uid:[0-9]+}]', EnterpriseController::class . ':opportunities_delete');

})->add(RestrictGuests::class);

$app->group('/enterprise', function (RouteCollectorProxy $group) {
    $group->get ('/opportunities', EnterpriseController::class . ':opportunities_list_ui')->setName('enterprise.opportunities'); 
    $group->get ('/projects', EnterpriseController::class . ':projects_list_ui')->setName('enterprise.projects'); 
})->add(RedirectGuests::class);

