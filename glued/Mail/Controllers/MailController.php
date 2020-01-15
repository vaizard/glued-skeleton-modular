<?php

declare(strict_types=1);

namespace Glued\Mail\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Core\Controllers\AbstractTwigController;
use Respect\Validation\Validator as v;
use Glued\Core\Classes\Crypto\Crypto as Crypto; // <------------------------------------------ crypto functions ready here

class MailController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function mail_manage_ui(Request $request, Response $response, array $args = []): Response
    {

       
        return $this->render($response, 'Mail/Views/opera.twig', [
            'pageTitle' => 'Opera',
        ]);
    }

    public function mail_opera_ui(Request $request, Response $response, array $args = []): Response
    {

        return $this->render($response, 'Mail/Views/opera.twig', [
            'pageTitle' => 'Opera',
        ]);
    }

    public function accounts_list(Request $request, Response $response, array $args = []): Response
    {
        $domains = $this->db->get('t_mail_accounts');

        
        return $this->render($response, 'Mail/Views/opera.twig', [
            'pageTitle' => 'Opera',
        ]);
    }

    public function accounts_get(Request $request, Response $response, array $args = []): Response
    {
        return $this->render($response, 'Mail/Views/opera.twig', [
            'pageTitle' => 'Opera',
        ]);
    }

    public function accounts_post(Request $request, Response $response, array $args = []): Response
    {

        $crypto = new Crypto;
        echo $this->settings['crypto']['mail'].'<br>';
        $secret = 'this is very secret vagene';
        echo $secret.'<br>';
        $en = $crypto->encrypt($secret, $this->settings['crypto']['mail']);
        echo $en.'<br>';
        $de = $crypto->decrypt($en, $this->settings['crypto']['mail']);
        echo $de.'<br>';


    }



}
