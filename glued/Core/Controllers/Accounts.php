<?php

declare(strict_types=1);

namespace Glued\Core\Controllers;

use Glued\Core\Classes\Auth\Auth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Accounts extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    /*
    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        if (isset($args['uid'])) {
            // list
        } else {
            // object
            $uid = isset($args['uid']);
            if (v::inval()->positive()->validate($uid)) {
                // show
                $auth = new Auth($this->db);
                $users = $auth->get($uid);
            } else {
                // forbidden value
            }
        }    
        return $this->render($response, 'Core/Views/accounts.twig', [
            'pageTitle' => 'Accounts',
            'users' => $users
        ]);
    }
*/
    public function read(Request $request, Response $response, array $args = []): Response
    {
        $auth = new Auth($this->db);
        $users = $auth->get($request, $args['uid']);

        return $this->render($response, 'Core/Views/accounts.read.twig', [
            'pageTitle' => 'Accounts',
            'users' => $users
        ]);

  /*
        // DO RBAC HERE
        if (v::inval()->positive()->validate($uid)) {
            // show
            $auth = new Auth($this->db);
            $users = $auth->get($uid);
            return $this->render($response, 'Core/Views/accounts.get.twig', [
                'pageTitle' => 'Accounts',
                'users' => $users
            ]);
        } else {
            // forbidden value
            // 
        }         
        */
    }

    public function list(Request $request, Response $response, array $args = []): Response
    {
        // DO RBAC HERE
        $auth = new Auth($this->db);
        $users = $auth->list($request);
        return $this->render($response, 'Core/Views/accounts.list.twig', [
            'pageTitle' => 'Accounts',
            'users' => $users
        ]);
    }




}


