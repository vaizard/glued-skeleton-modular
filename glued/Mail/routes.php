<?php
use Glued\Core\Middleware\RedirectGuests;
use Glued\Mail\Controllers\MailController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/mail', function (RouteCollectorProxy $group) {
    $group->get ('/accounts[/{uri}]', MailController::class . ':mail_opera_ui')->setName('mail.accounts.web'); 
    $group->get ('/opera[/{uri}]', MailController::class . ':mail_opera_ui')->setName('mail.opera.web');
})->add(RedirectGuests::class);
