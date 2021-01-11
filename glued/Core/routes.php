<?php
use Geocoder\geocode;
use Glued\Core\Classes\Utils\Utils;
use Glued\Core\Controllers\Accounts;
use Glued\Core\Controllers\AuthController;
use Glued\Core\Controllers\DomainsController as Domains;
use Glued\Core\Controllers\Glued;
use Glued\Core\Controllers\GluedApi;
use Glued\Core\Controllers\Profiles;
use Glued\Core\Controllers\Integrations;
use Glued\Core\Controllers\ProfilesApi;
use Glued\Core\Middleware\RedirectAuthenticated;
use Glued\Core\Middleware\RedirectGuests;
use Glued\Core\Middleware\RestrictGuests;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Symfony\Component\DomCrawler\Crawler;


// Homepage
$app->get('/', Glued::class)->setName('core.web');



$app->group('/core', function (RouteCollectorProxy $route) {
    // Everyone or Guests-only
    $route->group('', function ($route) {
        $route->get ('/signin', AuthController::class . ':signin_get')->setName('core.signin.web')->add(RedirectAuthenticated::class);
        $route->post('/signin', AuthController::class . ':signin_post');
        $route->get ('/reset[/{token}]', AuthController::class . ':reset_get')->setName('core.reset.web')->add(RedirectAuthenticated::class);
        $route->post('/reset', AuthController::class . ':reset_post');
        $route->get ('/signup', AuthController::class . ':signup_get')->setName('core.signup.web')->add(RedirectAuthenticated::class);
        // TODO consider killing the RedirectAuthenticated middleware. Just warn user instead?
    });
    // Authenticated-only
    $route->group('', function (RouteCollectorProxy $route) {
        $route->get('/dashboard', Glued::class)->setName('core.dashboard.web');
        $route->get('/profiles[/{uid}]', Profiles::class)->setName('core.profiles.list.web');
        $route->get('/accounts', Accounts::class . ':list') ->setName('core.accounts.list.web');
        $route->get('/accounts/{uid:[0-9]+}', Accounts::class . ':read')->setName('core.accounts.read.web');
        $route->patch('core/accounts/{uid:[0-9]+}/password', AuthController::class . ':change_password')-> setName('core.settings.password.web');
        $route->get('/domains', Domains::class . ':ui_manage')->setName('core.domains');
        $route->get ('/admin/phpinfo', function(Request $request, Response $response) { phpinfo(); return $response; }) -> setName('core.admin.phpinfo.web');
        $route->get ('/admin/phpconst', function(Request $request, Response $response) { highlight_string("<?php\nget_defined_constants() =\n" . var_export(get_defined_constants(true), true) . ";\n?>"); return $response; }) -> setName('core.admin.phpconst.web');
        $route->get('/integrations/google', Integrations::class . ':google')->setName('core.integrations.google');
        $route->get('/integrations/google_app', Integrations::class . ':google_app');
        $route->get('/integrations/fio_cz', Integrations::class . ':fio_cz')->setName('core.integrations.fio_cz');
    })->add(RedirectGuests::class);
});

// We have to have $app->post('/core/signup') outside the RedirectAuthenticated, becuase user gets signed in upon signup.
// Since the signup page submits data with ajax, the json response will get replaced with the redirect prematurely.
$app->post('/core/signup', AuthController::class . ':signup_post'); 
$app->get ('/core/signout', AuthController::class . ':signout_get')->setName('core.signout.web');

$app->group('/api/core/v1', function (RouteCollectorProxy $route) {
    // Everyone or Guests-only
    $route->group('', function (RouteCollectorProxy $route) {
        $route->get ('/auth/extend', AuthController::class . ':api_extend_get')->setName('core.auth.extend.api');
        $route->get ('/auth/whoami', AuthController::class . ':api_status_get')->setName('core.auth.whaomi.api');
        $route->post('/auth/signin', AuthController::class . ':api_signin_post')->setName('core.auth.signin.api');
        $route->get ('/auth/signout', AuthController::class . ':api_signout_get')->setName('core.auth.signout.api');
    });
    // Authenticated-only
    $route->group('', function (RouteCollectorProxy $route) {
        $route->get ('/test', GluedApi::class) ->                        setName('core.api');
        $route->post('/profiles', ProfilesApi::class . ':create') ->     setName('core.profiles.create.api01');
        $route->get ('/profiles', ProfilesApi::class . ':list') ->       setName('core.profiles.list.api01');
        $route->get ('/profiles/{uid:[0-9]+}', 'ApiProfiles:read') ->    setName('core.profiles.read.api01');
        $route->put ('/profiles/{uid:[0-9]+}', 'ApiProfiles:update') ->  setName('core.profiles.update.api01');
        $route->get ('/domains', Domains::class . ':list') ->            setName('core.domains.api01');
        $route->post('/domains', Domains::class . ':create');
    })->add(RestrictGuests::class);


});

$app->get ('/core/admin/playground', function(Request $request, Response $response) { 
    $reader = new \GeoIp2\Database\Reader(__ROOT__ . '/private/data/core/maxmind-geolite2-city.mmdb');
    $adapter = new \Geocoder\Provider\GeoIP2\GeoIP2Adapter($reader);
    $provider = new \Geocoder\Provider\GeoIP2\GeoIP2($adapter);

    $results = $provider->geocodeQuery(Geocoder\Query\GeocodeQuery::create('74.200.247.59'))->first()->getCountry()->getCode();
    print("<pre>".print_r($results,true)."</pre>");
    
    $pass = 'pwpw';
    $hash = sodium_crypto_pwhash_str($pass, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
    echo '<br>sodium_crypto_pwhash_str ' . $hash;

    
    //echo '<br>sodium_crypto_pwhash ' . sodium_crypto_pwhash($pass);
    echo '<br>';

    $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // 256 bit
    
    echo sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
    echo ' '.time();
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


        $settings['curl'] = [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:81.0) Gecko/20100101 Firefox/81.0',
            CURLOPT_COOKIEJAR => __ROOT__.'/private/cache/cookies.txt',
            CURLOPT_COOKIEFILE => __ROOT__.'/private/cache/cookies.txt',
            CURLOPT_POST => 0,
        ];


        $curl_handle = curl_init();
        $uri = 'https://japex01.vaizard.xyz/core/signin';
        $user = 'a@b.c';
        $pass = $user;
        $arr = [
            CURLOPT_URL => $uri,
            CURLOPT_COOKIESESSION => TRUE,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => 'email='.$user.'&password='.$pass,
            CURLOPT_FOLLOWLOCATION => true,
        ];


        $curl_options = array_replace( $settings['curl'], $arr );
        curl_setopt_array($curl_handle, $curl_options);
        $data1 = curl_exec($curl_handle);

        $uri = 'https://japex01.vaizard.xyz/api/core/v1/test';
        $arr = [
            CURLOPT_URL => $uri,
            CURLOPT_COOKIESESSION => FALSE,
        ];
        $curl_options = array_replace( $settings['curl'], $arr );
        print_r($curl_options);
        curl_setopt_array($curl_handle, $curl_options);
        $data2 = curl_exec($curl_handle);



//echo $data1;
echo "<br>-----------------------------<br>";
echo $data2;
        curl_close($curl_handle);
echo "<br>-----------------------------<br>";
//https://ib.fio.cz/ib/wicket/resource/cz.fio.ib2.prehledy.web.pohyby.PohybDetailPage$1/potvrzeni?pohybId=22011239954&cisloUctu=2500781658

//$u = new Utils($this->db, $this->settings);
//$data = $u->fetch_uri('https://ib.fio.cz/ib/login?l=CZECH');
//$c = new Crawler($data);
//$final = $c->filter('form .horizontal');
//echo $final;



//id36d6b105e9971ab1a_hf_0=&clientTimeDelta=677&psd2paymentId=&username=killua&passwordHidden=kikLam3upl%21tric81237siud19&p%3A%3Asubmit=


        //$curl_handle = curl_init();
        $uri = 'https://ib.fio.cz/ib/login?-1.IFormSubmitListener-loginForm';
        $user = 'killua';
        $pass = 'kikLam3upl!tric81237siud19';
        $arr = [
            CURLOPT_URL => $uri,
            CURLOPT_COOKIESESSION => TRUE,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => 'email='.$user.'&password='.$pass,
            CURLOPT_FOLLOWLOCATION => true,
        ];











        // https://www.srijan.net/blog/integrating-google-sheets-with-php-is-this-easy-know-how
        $client = new \Google_Client();
        $client->setApplicationName('Google Sheets and PHP');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig(__ROOT__ . '/private/api/glued-dev-91338368ae7d.json');
        $service = new Google_Service_Sheets($client);
        // https://docs.google.com/spreadsheets/d/1oxELlgVRr5ro3NyVeH-bT_GvGh72FjazaLTT9VDMmtc/edit#gid=0
        $spreadsheetId = "1oxELlgVRr5ro3NyVeH-bT_GvGh72FjazaLTT9VDMmtc"; //It is present in your URL
        $get_range = "Sheet1!A1:G5";
        $gresponse = $service->spreadsheets_values->get($spreadsheetId, $get_range);
        $values = $gresponse->getValues();
        print_r($values);

        // https://drive.google.com/file/d/1pPezoLPc2s8BIuIXl3l24PDhrViLjRdU/view?usp=sharing
        $client->addScope(\Google_Service_Drive::DRIVE);
        $service = new Google_Service_Drive($client);
        $fileId = "1pPezoLPc2s8BIuIXl3l24PDhrViLjRdU"; // Google File ID

        // Retrieve filename.
        $file = $service->files->get($fileId);
        $fileName = $file->getName();

        // Download a file.
        $content = $service->files->get($fileId, array("alt" => "media"));
        $handle = fopen(__ROOT__ . '/private/cache/'.$fileName, "w+"); // Modified
        while (!$content->getBody()->eof()) { // Modified
            fwrite($handle, $content->getBody()->read(1024)); // Modified
        }
        fclose($handle);
        echo "success";

        // List files
        echo "<br>folder view (add id to code):";
        $folderId = "1cw3a9jhPmJUbmU7-cp7j0y1h54eK6VY5";
        if ($folderId != "") {
            $parameters = array(
            'pageSize' => 100,
                  'q' => "'".$folderId."' in parents"
                );
            $results = $service->files->listFiles($parameters);
            echo '<table>';
            foreach($results as $file){
                print("<pre>".print_r($file,true)."</pre>");
                echo '<tr><td>ico <img src="'. $file->iconLink .'"/> file ' . $file->name . ' ' . $file->id . ' ' . $file->mimeType . ' ' . $file->md5Checksum . ' ' . $file->size. ' ' . $file->parents . '</td></tr>';
            }
            echo '</table>';
        }
    return $response;
}) -> setName('core.admin.playground.web');

$app->post ('/core/admin/playground', function(Request $request, Response $response) { 
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

$app->put ('/core/admin/playground', function(Request $request, Response $response) { 
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


