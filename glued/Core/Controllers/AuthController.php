<?php


declare(strict_types=1);

namespace Glued\Core\Controllers;
use Glued\Core\Classes\Auth\Auth;
use Glued\Core\Classes\JsonResponse\JsonResponseBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController extends AbstractTwigController

{

    public function signout_get($request, $response)
    {
        $this->auth->signout();
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }


    public function signin_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/signin.twig');
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

        $this->flash->addMessage('info', 'Welcome back, you are signed in!');
        return $response->withRedirect($this->routerParser->urlFor('core.dashboard.web'));
    }


    public function signup_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/signup.twig');
    }


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



    // responds to the change password post request (tries to change user's
    // password, redirects him to different locations based on success|failure.
    public function change_password_post($request, $response)
    {
        $user_id = $_SESSION['core_user_id'] ?? false;
        $auth_id = $_SESSION['core_auth_id'] ?? false;
        
        if ($user_id and $auth_id) {
            
            // matchesPassword() is a custom validation rule, see Classes/Validation
            // using $this->container->auth->user() as its parameter is a
            // preparation for cases when user's password can be reset by an admin
            // as well (not only the user himselft)
            
            // zatim udelame jen nejjednodussi pripad, ze menime heslo prihlaseneho uzivatele. pozdeji zde bude nejake vetveni
            $change_user_id = $user_id;
            $change_auth_id = $auth_id;
            
            $validation = $this->container->validator->validate($request, [
                'password_old' => v::noWhitespace()->notEmpty()->matchesPassword($this->container, $change_user_id, $change_auth_id),
                'password' => v::noWhitespace()->notEmpty(),
            ]);
            
            // on validation failure redirect back to the form. the rest of this
            // function won't get exectuted
            if ($validation->failed()) {
                $this->container->logger->warn("Password change failed. Validation error.");
                return $response->withRedirect($this->container->router->pathFor('auth.settings'));
            }
            
            // change the password, emit flash message and redirect
            $password = $request->getParam('password');
            $this->container->db->where('c_type', 1);
            $this->container->db->where('c_uid', $change_authentication_id);
            $this->container->db->where('c_user_id', $change_user_id);
            $update = $this->container->db->update('t_authentication', Array ( 'c_pasword' => password_hash($password, PASSWORD_DEFAULT)  ));
            
            if (!$update) {
                $this->container->logger->warn("Password change failed. DB error.");
                return $response->withRedirect($this->container->router->pathFor('auth.settings'));
            }
            else {
                $this->container->flash->addMessage('info', 'Your password was changed');
                return $response->withRedirect($this->container->router->pathFor('home'));
            }
        }
        
    }

}