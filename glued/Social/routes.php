<?php
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Glued\Core\Middleware\AntiXSSMiddleware;
use Glued\Social\Controllers\SocialController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/social/v1', function (RouteCollectorProxy $group) {
    $group->post ('/token', SocialController::class . ':fb_token_get')->setName('social.fb.token.get');
    $group->post ('/me', SocialController::class . ':fb_profile_get_me')->setName('social.fb.me.get');
})->add(RestrictGuests::class);

$app->group('/social', function (RouteCollectorProxy $group) {
    $group->get ('/fb/profile', SocialController::class . ':fb_profile')->setName('social.fb.profile');
    $group->get ('/fb/return/code', SocialController::class . ':fb_return_code')->setName('social.fb.return.code');
})->add(RedirectGuests::class);

