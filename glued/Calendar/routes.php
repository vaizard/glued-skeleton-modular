<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Calendar\Controllers\CalendarController;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Define the app routes.
$app->group('/calendar', function (RouteCollectorProxy $group) {
    $group->get ('/browse', CalendarController::class)->setName('calendar.browse.web'); 
    $group->get ('/manage', CalendarController::class)->setName('calendar.manage.web'); 
});

/*
t_store_opportunities
t_store_offers
t_store_orders
t_store_cart
t_store_invoices
t_store_disputes

t_calendar_uris
t_worklog_items
t_mail_accounts
t_mail_box
t_mail_cards
t_mail_attachments

t_spider_subscriptions
t_spider_data
t_spider_events
*/


