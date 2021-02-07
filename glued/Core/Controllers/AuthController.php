<?php


declare(strict_types=1);

namespace Glued\Core\Controllers;
use Firebase\JWT\JWT;
use Glued\Core\Classes\Auth\Auth;
use Glued\Core\Classes\Crypto\Crypto;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use \Exception;

class AuthController extends AbstractTwigController

{

    public function signout_get($request, $response)
    {
        $this->auth->signout();
        $this->flash->addMessage('warning', 'You have been signed out.');
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }


    public function reset_get($request, $response, array $args = [])
    {
        $twig = 'Core/Views/reset.twig';


        if (isset($args['token'])) {
            $twig = 'Core/Views/reset-token-fail.twig'; 
            $this->db->where("c_token", $args['token']);
            $this->db->where("c_ts_timeout >= timestamp(NOW())");
            $reset = $this->db->get("t_core_authn_reset", null);
            if ($reset) $twig = 'Core/Views/reset-token-pass.twig'; 
        }
        return $this->view->render($response, $twig, [
            'redirect' => $request->getParams()['redirect'] ?? null,
            'token' => $args['token'] ?? null,
        ]);
    }


    public function reset_post($request, $response)
    {
        $builder = new JsonResponseBuilder('auth-reset', 1);
                //print_r($request->getParsedBody()); die();


        // TODO verify that a posted json will yield same results as XHR posting a form
        // TODO add throttling for too many reset requests for diff accounts from one source and/or overall
        $validation = $this->validator->validate($request, [
            'email' => v::noWhitespace()->notEmpty()->email(),
        ]);

        if ($this->auth->check()) {
            $payload = $builder->withMessage(__('You are signed in, please sign out first.'))->build();
            return $response->withJson($payload, 403); 
        }

        if ($validation->failed()) {
            $reseed = $this->validator->reseed($request, [ 'email' ]);
            $payload = $builder->withValidationError($validation->messages())
                               ->withValidationReseed($reseed)
                               ->build();
            return $response->withJson($payload, 400);
        } else {

            // Fetch user
            $email = $request->getParam('email');
            $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
            $this->db->where("u.c_email", $email);
            $this->db->where("a.c_type", 0);
            $user = $this->db->get("t_core_users u", null);

            // Lame throttling + token insert
            $seconds_test = 60;
            $seconds_step = 5;
            $seconds_max_throttle = 30;
            $seconds_token_valid = 3600;
            
            if (!$user) {
                $seconds_throttle = $seconds_max_throttle;
            } else {
                $reset = $this->db->rawQuery("SELECT * FROM t_core_authn_reset WHERE c_user_id = ? AND c_auth_id = ? AND c_ts_created BETWEEN timestamp(DATE_SUB(NOW(), INTERVAL ? SECOND)) AND timestamp(NOW())", [ $user[0]['c_user_uid'], $user[0]['c_uid'], $seconds_test ]);

                $seconds_throttle = count($reset) * $seconds_step;
                if ($seconds_throttle > $seconds_max_throttle) $seconds_throttle = $seconds_max_throttle;

                $data = [
                    "c_user_id" => $user[0]['c_user_uid'],
                    "c_auth_id" => $user[0]['c_uid'],
                    "c_token"   => $this->crypto->genkey_base64(),
                    "c_ts_timeout" => $this->db->func("(DATE_ADD( NOW(), INTERVAL $seconds_token_valid SECOND))")
                ];
                $this->db->insert("t_core_authn_reset", $data);
            
                try {
                    $uri = $_SERVER['SERVER_NAME'] ?? '';
                    if ($uri == '') {
                        $payload = $builder->withMessage(__('Password resets currently impossible.'))->withCode(500)->build();
                        return $response->withJson($payload, 500);
                    }
                    $uri = 'https://' . $uri . $this->routerParser->urlFor('core.reset.web') . '/' . $data['c_token'];
                    $message = (new \Swift_Message())
                      ->setSubject('Password reset')
                      ->setFrom([ $this->settings['smtp']['from'] ])
                      ->setTo([ $email ])
                      ->setBody(
                            '<html><body>' .
                            '<p>A password reset was requested on your behalf.</p>' .
                            '<p>If this was you, <a href="'.$uri.'">click this link</a> to proceed with the password reset.</p>' .
                            '</body></html>',
                            'text/html'
                        );
                    $this->mailer->send($message);
                    
                } catch (\Exception $e) {
                    $payload = $builder->withMessage(__('Sending the password reset e-mail failed.'))->withCode(500)->build();
                    return $response->withJson($payload, 500);
                }
            }
            sleep($seconds_throttle);

/*
            // TODO log attempt
            $this->auth->reset($request->getParam('email')); // auto sign-in after account creation
            */
            $msg = 'A password reset token has been sent to you. Please follow the instructions received by e-mail.';
            $payload = $builder->withMessage($msg)->withCode(200)->build();
            return $response->withJson($payload, 200);
        }
    }

    public function signin_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/signin.twig', [
            'redirect' => $request->getParams()['redirect'] ?? null 
        ]);
    }





    public function api_signout_get($request, $response)
    {
        $builder = new JsonResponseBuilder('authentication', 1);
        $this->auth->signout();
        return $response->withJson(['status' => 'OK', 'message' => 'Signed out.']);

    }

    public function api_extend_get($request, $response) {
        $builder = new JsonResponseBuilder('authentication', 1);
        $auth = $this->auth->check();
        if (!$auth) {
            $payload = $builder->withMessage(__('Authentication failed.'))->withCode(403)->build();
            return $response->withJson($payload, 403);    
        }
        $token = $this->auth->jwt_extend($GLOBALS['_JWT']);
        return $response->withJson(['status' => 'OK', 'message' => 'Extended.', 'token' => $token]);
    }

    public function api_status_get($request, $response) {
        $builder = new JsonResponseBuilder('auth-status', 1);
        $header_match = "Authorization";
        $regexp_match = "/Bearer\s+(.*)$/i";

        // session response + jwt defaults
        $payload = [
            'jwt' => [
                'in_header' => false,
                'in_cookie' => false,
                'authenticated' => false,
                'raw' => '',

            ],
            'session' => [
                'in_header' => false,
                'in_cookie' => session_name(),
                'authenticated' => $this->auth->check(),
                'id' => session_id(),
                'status' => session_status(),
            ],
        ];

        // get token from cookie
        $payload['jwt']['raw'] = $_COOKIE[$this->settings['auth']['jwt']['cookie']] ?? null;
        if ($payload['jwt']['raw']) $payload['jwt']['in_cookie'] = $this->settings['auth']['jwt']['cookie'];

        // get token from header
        $header = $request->getHeaderLine($header_match);
        if (false === empty($header)) {
            $payload['jwt']['in_header'] = $header_match;
            if (preg_match($regexp_match, $header, $matches)) {
                $payload['jwt']['raw'] = $matches[1];
            }
        }

        // parse token
        if ($payload['jwt']['raw']) {
            try {
                $decoded = (array)JWT::decode($payload['jwt']['raw'], $this->settings['auth']['jwt']['secret'], [ $this->settings['auth']['jwt']['algorithm'] ]);
                $payload['jwt']['decoded']['___'] = "Most claims won't print here, sorry.";
                if (($decoded['g_uid'] > 0) and ($decoded['g_aid'] > 0)) $payload['jwt']['authenticated'] = true;
                $payload['jwt']['decoded']['iat'] = $decoded['iat'];
                $payload['jwt']['decoded']['exp'] = $decoded['exp'];
            } catch (Exception $exception) {
                $payload['jwt']['error'] = $exception->getMessage();
            }
        }

        $code = 401;
        if ($payload['session']['authenticated']) $code = 200;
        if ($payload['jwt']['authenticated']) $code = 200;
        $payload = $builder->withData($payload)->withCode($code)->build();
        return $response->withJson($payload, $code);  
    }

    public function api_signin_post($request, $response)
    {
        $builder = new JsonResponseBuilder('authentication', 1);
        $auth = $this->auth->attempt(
            $request->getParam('email'),
            $request->getParam('password')
        );

        if (!$auth) {
            $payload = $builder->withMessage(__('Authentication failed.'))->withCode(403)->build();
            return $response->withJson($payload, 403);    
        }

        return $response->withJson(['status' => 'OK', 'message' => 'Signed in.', 'token' => $auth]);
    }

    public function signin_post($request, $response)
    {
        $auth = $this->auth->attempt(
            $request->getParam('email'),
            $request->getParam('password')
        );
                    
        if (!$auth) {
            //$this->events->emit('core.install.migration.addrbac', [$auth_id]);
            $this->flash->addMessage('error', 'Could not sign you in with those details.');
            return $response->withRedirect($this->routerParser->urlFor('core.signin.web'));
        }

        // If an unauthenticated user visits an URI requiring authentication, he'll
        // be redirected by the RedirectGuest middleware to the signin page. The
        // middleware will encrypt the URI the user wanted to visit and will pass
        // it to an <input type=hidden> on the signin page. We fetch the data below
        // into $enc and try to decrypt it. The try/catch will deal with decryption
        // errors caused by a truncated get paramter (i.e. user copying an incomplete
        // link and using it later/elsewhere). The if (!redirect) deals with garbled
        // get param (i.e. string length is correct, but user changed a letter or two).


        if ($enc = $request->getParam('redirect')) {
            $crypto = new Crypto;
            try {
                $redirect = $crypto->decrypt($enc, $this->settings['crypto']['reqparams']);
                if (!$redirect) { $redirect = $this->routerParser->urlFor('core.dashboard.web'); }
            } catch (Exception $e) {
                $redirect = $this->routerParser->urlFor('core.dashboard.web');
            }
        } else {
            $redirect = $this->routerParser->urlFor('core.dashboard.web'); 
        }
        $this->flash->addMessage('info', 'Welcome back, you are signed in!');
        return $response->withRedirect($redirect);
    }



    public function signup_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/signup.twig', []);
    }


    public function signup_post($request, $response)
    {
        $builder = new JsonResponseBuilder('authentication', 1);

        // TODO verify that a posted json will yield same results as XHR posting a form
        $validation = $this->validator->validate($request, [
            'email' => v::noWhitespace()->notEmpty()->email()->emailAvailable($this->db),
            'name' => v::notEmpty()->alnum(),
            'password' => v::noWhitespace()->notEmpty(),
        ]);

        if ($this->auth->check()) {
            $payload = $builder->withMessage(__('Please sign out to sign up.'))->build();
            return $response->withJson($payload, 403);            
        }
        if ($validation->failed()) {
            $reseed = $this->validator->reseed($request, [ 'email', 'name' ]);
            $payload = $builder->withValidationError($validation->messages())
                               ->withValidationReseed($reseed)
                               ->build();
            return $response->withJson($payload, 400);
        } else {
            $auth_id = $this->auth->user_create($request->getParam('email'), $request->getParam('name'), $request->getParam('password'));
            if ($auth_id) $this->events->emit('core.auth.user.create', [$auth_id]);
            $this->auth->attempt($request->getParam('email'), $request->getParam('password')); // auto sign-in after account creation

            $msg = __('You were signed up successfully. We signed you in too!');
            $payload = $builder->withMessage($msg)->withCode(200)->build();
            $this->flash->addMessage('info', $msg);
            return $response->withJson($payload, 200);
        }
    }

/*
    public function signup_post($request, $response)
    {
        $validation = $this->validator->validate($request, [
            'email' =>v::noWhitespace()->notEmpty()->email()->emailAvailable($this->db),
            'name' => v::notEmpty()->alnum(),
            'password' => v::noWhitespace()->notEmpty(),
        ]);
        if ($validation->failed()) {
            if ($request->isXhr() === true) {
                $builder = new JsonResponseBuilder('authentication', 1);
                $payload = $builder->withValidationError($validation->messages())->build();
                return $response->withJson($payload, 400);
                // TODO With the ajax form submit via the twig view (see signup_get()) the browser's console log
                // sees a 302 redirect, but the page doesn't reload (even if it should per the jquery success callback)
            } else {
                return $response->withRedirect($this->routerParser->urlFor('core.signup.web'));
            }
        }
        $this->auth->user_create($request->getParam('email'), $request->getParam('name'), $request->getParam('password'));
        $this->flash->addMessage('info', 'You have been signed up!');
        $this->auth->attempt($request->getParam('email'), $request->getParam('password')); // auto sign-in after account creation
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }
 */


    // responds to the change password post request (tries to change user's
    // password, redirects him to different locations based on success|failure.
    public function change_password($request, $response)
    {
        $user_id = $GLOBALS['_GLUED']['authn']['user_id'] ?? false;
        $auth_id = $GLOBALS['_GLUED']['authn']['auth_id'] ?? false;

        // TODO - this fires on password reset
        
        if ($user_id and $auth_id) {
            
            // matchesPassword() is a custom validation rule, see Classes/Validation
            // using $this->auth->user() as its parameter is a
            // preparation for cases when user's password can be reset by an admin
            // as well (not only the user himself)
            
         
            $validation = $this->validator->validate($request, [
                'password_old' => v::noWhitespace()->notEmpty()->matchesPassword($this->db, $user_id, $auth_id),
                'password' => v::noWhitespace()->notEmpty(),
            ]);
            
            // on validation failure redirect back to the form. the rest of this
            // function won't get exectuted
            if ($validation->failed()) {
                $this->logger->warn("Password change failed. Validation error.");
                return $response->withRedirect($this->routerParser->urlFor('core.accounts.read.web',['uid' => $user_id]));
            }
            
            // change the password, emit flash message and redirect
            $update = $this->auth->cred_update($user_id, $auth_id, $request->getParam('password'));
            
            if (!$update) {
                $this->logger->warn("Password change failed. DB error.");
                return $response->withRedirect($this->routerParser->urlFor('core.accounts.read.web',['uid' => $user_id]));
            }
            else {
                $this->logger->info("One password changed.");
                $this->flash->addMessage('info', 'Your password was changed');
                return $response->withRedirect($this->routerParser->urlFor('core.accounts.read.web',['uid' => $user_id]));
            }
        }
        
    }

}