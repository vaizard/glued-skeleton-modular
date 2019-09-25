<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Classes\Auth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountsController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $uid = isset($args['uid']);
        
        if ($uid == "") {
          $auth = new Auth($this->db);
          $users = $auth->get($uid);
        }
        elseif ($uid > 0) // TODO do propper validation here
        {
          //$uid = intval($uid);
          $auth = new Auth($this->db);
          $users = $auth->get($uid);
        }            
        else {
            echo "nope";
            return $response;
        }
        
        return $this->render($response, 'Core/Views/accounts.twig', [
            'pageTitle' => 'Accounts',
            'users' => $users
        ]);
    }
}


