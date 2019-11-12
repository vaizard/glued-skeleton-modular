<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Core\Controllers\Glued;
use Glued\Core\Controllers\GluedApi;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Glued\Core\Controllers\Accounts;
use Glued\Core\Controllers\Profiles;
use Glued\Core\Controllers\ProfilesApi;


// Define the app routes.
$app->group('/', function (RouteCollectorProxy $group) {
    $group->get('', Glued::class)->setName('core.web');
    $group->get ('core/dashboard', Glued::class)->setName('core.dashboard.web')->add(new RedirectIfNotAuthenticated());

    // TODO What's the problem here?
    //$group->get('core/signin', HomeController::class)->setName('web.core.signin')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));
    //$group->get('core/signup', HomeController::class)->setName('web.core.signup')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));


    $group->get ('core/signout', Glued::class) ->                               setName('core.signout.web');
    $group->get ('core/profiles[/{uid}]', Profiles::class) ->                   setName('core.profiles.list.web');
    $group->get ('api', GluedApi::class) ->                                    setName('core.api');
    $group->post('api/core/v1/profiles', ProfilesApi::class . ':create') ->     setName('core.profiles.create.api01');
    $group->get ('api/core/v1/profiles', ProfilesApi::class . ':list') ->       setName('core.profiles.list.api01');
    $group->get ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:read') ->    setName('core.profiles.read.api01');
    $group->put ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:update') ->  setName('core.profiles.update.api01');
    $group->get ('core/accounts', Accounts::class . ':list') ->                 setName('core.accounts.list.web');
    $group->get ('core/accounts/{uid}', Accounts::class . ':read') ->           setName('core.accounts.read.web');
    $group->get ('json', JsonController::class)->setName('api.core.json');

    /* OLD
    $group->get ('core/signout', HomeController::class)->setName('web.core.signout');
    $group->get ('core/profiles[/{uid}]', WebProfiles::class)->setName('web.core.profiles.list');
    $group->post('api/core/v1/profiles', 'WebProfiles:create')->setName('api.core.v1.profiles.create');
    $group->get ('api/core/v1/profiles', ApiProfiles::class . ':list')->setName('api.core.v1.profiles.list');
    $group->get ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:read')->setName('api.core.v1.profiles.read');
    $group->put ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:update')->setName('api.core.v1.profiles.update');
    $group->get ('core/accounts', AccountsController::class . ':list')->setName('web.core.accounts.list');
    $group->get ('core/accounts/{uid}', AccountsController::class . ':read')->setName('web.core.accounts.read');
    $group->get ('json', JsonController::class)->setName('api.core.json');
    */
});

// why is $app inaccessible?
//$app->get('core/signin', HomeController::class)->setName('web.core.signin')->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));

// going to `/core/signin` should redirect via Middleware/RedirectIfAuthenticated.php
// to web.core.dashboard.  this should be `/core/dashboard`, but instead the readir leads
// to `/signin`
$app->get('/core/signin', Glued::class)->setName('core.signin.web')->add(new RedirectIfAuthenticated($container->get('routerParser')));

