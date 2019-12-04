<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Mail\Controllers\MailController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.
$app->group('/mail', function (RouteCollectorProxy $group) {
    $group->get ('/accounts[/{uri}]', MailController::class)->setName('mail.accounts.web'); // ->add(new RedirectIfNotAuthenticated());
    $group->get ('/opera[/{uri}]', MailController::class)->setName('mail.opera.web'); // ->add(new RedirectIfNotAuthenticated());
});
