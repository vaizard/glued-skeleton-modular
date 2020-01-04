<?php
use Slim\Routing\RouteCollectorProxy;
use Glued\Core\Controllers\Glued;
use Glued\Core\Controllers\GluedApi;
use Glued\Core\Middleware\RedirectIfAuthenticated;
use Glued\Core\Middleware\RedirectIfNotAuthenticated;
use Glued\Core\Controllers\Accounts;
use Glued\Core\Controllers\AuthController;
use Glued\Core\Controllers\Profiles;
use Glued\Core\Controllers\ProfilesApi;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// Define the app routes.
$app->group('/', function (RouteCollectorProxy $group) {
    $group->get('', Glued::class)->setName('core.web');
    $group->get ('core/dashboard', Glued::class)->setName('core.dashboard.web');//->add(new RedirectIfNotAuthenticated());

    // TODO What's the problem here?
    $group->get ('core/signin', AuthController::class . ':signin_get')->setName('core.signin.web');//->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));
    $group->post('core/signin', AuthController::class . ':signin_post');//->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() ));

    $group->get ('core/signup', AuthController::class . ':signup_get') ->        setName('core.signup.web');
    $group->post('core/signup', AuthController::class . ':signup_post');
    $group->get ('core/signout', AuthController::class . ':signout_get') ->     setName('core.signout.web');

    $group->get ('core/profiles[/{uid}]', Profiles::class) ->                   setName('core.profiles.list.web');
    $group->get ('api', GluedApi::class) ->                                     setName('core.api');
    $group->post('api/core/v1/profiles', ProfilesApi::class . ':create') ->     setName('core.profiles.create.api01');
    $group->get ('api/core/v1/profiles', ProfilesApi::class . ':list') ->       setName('core.profiles.list.api01');
    $group->get ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:read') ->    setName('core.profiles.read.api01');
    $group->put ('api/core/v1/profiles/{uid:[0-9]+}', 'ApiProfiles:update') ->  setName('core.profiles.update.api01');

    $group->get ('core/accounts', Accounts::class . ':list') ->                 setName('core.accounts.list.web');
    $group->get ('core/accounts/{uid:[0-9]+}', Accounts::class . ':read') ->           setName('core.accounts.read.web');
    $group->patch('core/accounts/{uid:[0-9]+}/password', AuthController::class . ':change_password')-> setName('core.settings.password.web');//->add(new RedirectIfAuthenticated( $app->getRouteCollector->getRouteParser() )); TODO / ano?

    $group->get ('core/admin/phpinfo', function(Request $request, Response $response) { phpinfo(); }) -> setName('core.admin.phpinfo.web');
    $group->get ('core/admin/phpconst', function(Request $request, Response $response) { highlight_string("<?php\nget_defined_constants() =\n" . var_export(get_defined_constants(true), true) . ";\n?>"); }) -> setName('core.admin.phpconst.web');
    $group->get ('core/admin/playground', function(Request $request, Response $response) { 

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // 256 bit
        echo base64_encode($key).' '.time();

        echo '
            <link rel="stylesheet" type="text/css" href="/assets/cache/styles.db34ce26cb19c04c315933041af50e77c292abb9.css" media="all" />
            <div id="validation_errors"></div>

            <div class="card card-primary col-4">
              <form action="/core/admin/playground" method="post" autocomplete="off" id="formpost">
                <input type="email" name="email" id="email" placeholder="you@domain.com" class="form-control" value="a@a.a">
                <input type="password" name="password" id="password" class="form-control" value="pwpw">
                <button type="submit" class="btn btn-primary">Form submit</button>
            </form>
            <form action="/core/admin/playground" method="post" autocomplete="off" id="formajax">
                <input type="email" name="email" id="email" placeholder="you@domain.com" class="form-control" value="a@a.a">
                <input type="password" name="password" id="password" class="form-control" value="pwpw">
                <button type="submit" class="btn btn-primary">Ajax submit</button>
            </form>
            <div id="twigAplaceholder"></div>
            {% raw %}
            <script type="text/twig" id="twigA">
                <form action="/core/admin/playground" method="post" autocomplete="off" id="formajaxput">
                    <input type="email" name="email" id="email_input" placeholder="you@domain.com" class="form-control{{ validation_errors.email ? \' is-invalid\' : validation_reseed.email ? \' is-valid\' : \'\' }}" value="{{ validation_reseed.email }}">
                    {% if validation_errors.email %}
                        <span class="invalid-feedback">{{ validation_errors.email | first }}</span>
                    {% endif %}
                    <input type="password" name="password" id="password" class="form-control" value="pwpw">
                    <button type="submit" class="btn btn-primary">Ajax submit PUT</button>                    
                </form>            
            </script>
            {% endraw %}
            <br>twig.js tests<br><br>
            <script type="text/twig" id="twigB">
              {% set animal = "fox" %}
              a quick brown {{ animal }}
              {{ validation_errors.email }}
              {{ validation_errors.name }}
              {{ validation_errors.password }}
              {{ list.1 }}

              <hr>
            </script>
            <div class="display-templates"></div>
            <script src="/assets/node_modules/jquery/dist/jquery.min.js" nonce="dummy_nonce"></script>
            <script src="/assets/node_modules/twig/twig.min.js" nonce="dummy_nonce"></script>
            <script src="/assets/node_modules/@claviska/jquery-ajax-submit/jquery.ajaxSubmit.min.js" nonce="dummy_nonce"></script>

            <script nonce="dummy_nonce">
                $("#formajax").ajaxSubmit({
                  success: function(res) {
                    console.log(res);
                    location.reload();
                  }
                });

                $("script[type=\'text/twig\']").each(function() {
                  var id = $(this).attr("id"),
                    data = $(this).text();

                  Twig.twig({
                    id: id,
                    data: data,
                    allowInlineIncludes: true
                  });
                });

                var listjs = ["one", "two", "three"];

                $(".display-templates").append(
                  Twig.twig({ ref: \'twigB\' }).render({ list: listjs })
                );

                $("#twigAplaceholder").append(
                  Twig.twig({ ref: \'twigA\' }).render({ list: listjs })
                );
            </script>            

            <script nonce="dummy_nonce">
                $("#formajaxput").ajaxSubmit({
                    headers: {
                        "X-Http-Method-Override": "PUT"
                    },                    
                    success: function(res) {
                        console.log(res);
                    },
                    error: function(res) {
                        $("#validation-errors").html("");
                        $("#validation_errors").empty();
                        $.each(res.message.validation_errors, function(key,value) {
                            $("#validation_errors").append("<div class=\'alert alert-danger\'>"+key+\' \'+value[0]+"</div");
                        }); 
                        $(".display-templates").append(
                            Twig.twig({ ref: \'twigB\' }).render({ validation_errors: res.message.validation_errors })
                        );
                        $(".display-templates").replaceWith(
                            Twig.twig({ ref: \'twigA\' }).render({ validation_errors: res.message.validation_errors })
                        );
                    }
                });
            </script>
            ';

        return $response;

    }) -> setName('core.admin.playground.web');
    $group->post ('core/admin/playground', function(Request $request, Response $response) { 
        $e = $request->getParam('email');
        $p = $request->getParam('password');
        $isXHR = $request->isXhr();
        $gpb = $request->getParsedBody();
        $upb = $request->getBody();
        $x = array( 'e' => $e, 'p' => $p, 'xhr' => $isXHR, 'gpb' => $gpb, 'upb' => htmlentities($upb) );
        if ($isXHR === true) {
            return $response->withJson($x);
        } else {
            //echo $upb;
            return $response->withJson($x);
            //return $response->withRedirect('http://10.146.149.29/core/admin/playground');
        }    
    });
    $group->put ('core/admin/playground', function(Request $request, Response $response) { 
        //validation_errors
        //$x = array( 'put' => 'success' );
        $x = [
                'success' => 'false', 
                'message' => [ 
                    'validation_errors' => [ 
                        'password' => [ 'must be longer', 'must be stronger' ],
                        'email' => [ 'must be nicer' ],
                    ]
                ]
            ];
        return $response->withJson($x)->withStatus(400);
    });

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
//$app->get('/core/signin', Glued::class)->setName('core.signin.web')->add(new RedirectIfAuthenticated($container->get('routerParser')));

