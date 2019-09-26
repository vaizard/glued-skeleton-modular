<?php
use App\Controllers\AccountsController;
use App\Controllers\HomeController;
use App\Controllers\JsonController;
use App\Middleware\RedirectIfAuthenticated;
use App\Middleware\RedirectIfNotAuthenticated;
use Slim\Routing\RouteCollectorProxy;

// Define the app routes.
$app->group('/', function (RouteCollectorProxy $group) {
    $group->get('', HomeController::class)->setName('web.home');
    $group->get('core/dashboard', HomeController::class)->setName('web.core.dashboard')->add( new RedirectIfNotAuthenticated());

    // TODO What's the problem here?
    //$group->get('core/signin', HomeController::class)->setName('web.core.signin')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));
    //$group->get('core/signup', HomeController::class)->setName('web.core.signup')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));

    $group->get('core/signout', HomeController::class)->setName('web.core.signout');
    $group->get('core/profiles[/{uid}]', ProfilesController::class)->setName('web.core.profiles');
    $group->get('core/accounts', AccountsController::class . ':list')->setName('web.core.accounts');
    $group->get('core/accounts/{uid}', AccountsController::class . ':get')->setName('web.core.accounts.obj');
    $group->get('json', JsonController::class)->setName('api.core.json');
});

// why is $app inaccessible?
//$app->get('core/signin', HomeController::class)->setName('web.core.signin')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));

// going to `/core/signin` should redirect via Middleware/RedirectIfAuthenticated.php
// to web.core.dashboard.  this should be `/core/dashboard`, but instead the readir leads
// to `/signin`
$app->get('/core/signin', HomeController::class)->setName('web.core.signin')->add(new RedirectIfAuthenticated( $container->get('routerParser') ));

