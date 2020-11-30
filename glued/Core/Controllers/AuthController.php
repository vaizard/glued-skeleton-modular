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


    public function reset_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/reset.twig', [
            'redirect' => $request->getParams()['redirect'] ?? null 
        ]);
    }


    public function reset_post($request, $response)
    {
        $builder = new JsonResponseBuilder('auth-reset', 1);

        // TODO verify that a posted json will yield same results as XHR posting a form
        // TODO add throttling for too many reset requests for diff accounts from one source and/or overall
        $validation = $this->validator->validate($request, [
            'email' => v::noWhitespace()->notEmpty()->email(),
        ]);

        if ($this->auth->check()) {
            $payload = $builder->withMessage(__('You are signed in.'))->build();
            return $response->withJson($payload, 403); 
            // TODO mozna by tudy mohl resetovat hesla root? nebo mu dame tlacitko           
        }
        if ($validation->failed()) {
            $reseed = $this->validator->reseed($request, [ 'email' ]);
            $payload = $builder->withValidationError($validation->messages())
                               ->withValidationReseed($reseed)
                               ->build();
            // TODO test if email exists
            // TODO test if throttling should apply on this particular email addr
            // TODO log attempt
            return $response->withJson($payload, 400);
        } else {
            $this->auth->reset($request->getParam('email')); // auto sign-in after account creation
            $flash = [
                "info" => 'A password reset token has been sent to you. Please follow the instructions received by e-mail.',
            ];
            $payload = $builder->withFlashMessage($flash)->withCode(200)->build();
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

            $flash = [
                "info" => 'You have been signed up',
                "info" => 'You have been signed in too'
            ];
            $payload = $builder->withFlashMessage($flash)->withCode(200)->build();
            $this->flash->addMessage('info', __('You were signed up successfully. We signed you in too!'));
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