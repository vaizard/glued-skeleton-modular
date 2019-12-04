<?php


declare(strict_types=1);

namespace Glued\Core\Controllers;
use Glued\Core\Classes\Auth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController extends AbstractTwigController

{
    public function signout_get($request, $response)
    {
        $this->auth->logout();
        return $response->withRedirect($this->router->pathFor('home'));
    }

    public function signin_get($request, $response)
    {
        return $this->view->render($response, 'auth/signin.twig');
    }

    public function signin_post($request, $response)
    {
        $auth = $this->auth->attempt(
            $request->getParam('email'),
            $request->getParam('password')
        );

        if (!$auth) {
            $this->flash->addMessage('error', 'Could not sign you in with those details.');
            return $response->withRedirect($this->router->pathFor('auth.signin'));
        }

        return $response->withRedirect($this->router->pathFor('home'));
    }

    public function signup_get($request, $response)
    {
        return $this->view->render($response, 'Core/Views/signup.twig');
    }

    public function signup_post($request, $response)
    {

        $validation = $this->validator->validate($request, [
            'email' => v::noWhitespace()->notEmpty()->email()->emailAvailable($this->db),
            'name' => v::notEmpty()->alnum(),
            'password' => v::noWhitespace()->notEmpty(),
        ]);

        if ($validation->failed()) {
            //print_r($validation->errors); die();
            return $response->withRedirect($this->routerParser->urlFor('core.signup.web'));
        }

        // transaction start
        $this->db->startTransaction();
        $trx_error = false;
  
        $data = array (
            'c_email' => $request->getParam('email'),
            'c_name'  => $request->getParam('name'),
        );
        if (!$this->db->insert ('t_core_users', $data)) { $trx_error = true; }
        // TODO add error message - email exists, screen name ...? send it over via $validation

        $subq = $this->db->subQuery()->where('c_email', $request->getParam('email'))->getOne('t_core_users', 'c_uid');
        $data = array (
            'c_type' => 0,
            'c_user_uid' => $subq,
            'c_hash' => password_hash($request->getParam('password'), $this->settings['php']['password_hash_algo'], $this->settings['php']['password_hash_opts']),
        );
        if (!$this->db->insert ('t_core_authn', $data)) { $trx_error = true; }
        // TODO add error message - db error ...? send it over via $validation
        
        if ($trx_error === true) { $this->db->rollback(); } 
        else { $this->db->commit(); }

       // TODO pass valid data back to form in case something is inalid (glued has old.email etc.)
       // TODO add custom email validator
       // TODO configure session middleware according to $settings
       // 
/*
        $this->flash->addMessage('info', 'You have been signed up!');
 =       $this->auth->attempt($user->email, $request->getParam('password')); */
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }
}
