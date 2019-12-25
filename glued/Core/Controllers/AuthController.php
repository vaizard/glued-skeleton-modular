<?php


declare(strict_types=1);

namespace Glued\Core\Controllers;
use Glued\Core\Classes\Auth\Auth;
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
/*
   public function test($request, $response) {

        $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
        $this->db->where("u.c_uid", 16);
        $this->db->where("a.c_uid", 9);
        $result = $this->db->getOne("t_core_users u", null);

        echo  $this->db->getLastQuery().'<br>';
        print_r($result);
        return $response;

   }
*/
    public function signup_post($request, $response)
    {

        $validation = $this->validator->validate($request, [
            'email' =>v::noWhitespace()->notEmpty()->email()->emailAvailable($this->db),
            'name' => v::notEmpty()->alnum(),
            'password' => v::noWhitespace()->notEmpty(),
        ]);
        if ($validation->failed()) {
            return $response->withRedirect($this->routerParser->urlFor('core.signup.web'));
        }
        $this->auth->user_create($request->getParam('email'), $request->getParam('name'), $request->getParam('password'));
        // signin user after account creation
        $this->auth->attempt($request->getParam('email'), $request->getParam('password'));

       // TODO configure session middleware according to $settings
/*
        $this->flash->addMessage('info', 'You have been signed up!');
 =       $this->auth->attempt($user->email, $request->getParam('password')); */
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }
}
