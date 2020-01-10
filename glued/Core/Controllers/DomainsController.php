<?php


declare(strict_types=1);

namespace Glued\Core\Controllers;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class DomainsController extends AbstractTwigController

{

    public function list($request, $response)
    {
        $this->auth->signout();
        return $response->withRedirect($this->routerParser->urlFor('core.web'));
    }


    public function create($request, $response)
    {
        $validation = $this->validator->validate($request, [
            'name' => v::notEmpty()->alnum(),
        ]);

        // TODO consider making domain names unique (duplicate domains will fuck up teams etc.) - probably just add an additional unique nonce
        // TODO do propper validation here
        if (!isset($_SESSION['core_user_id'])) { die('login first'); }

        if ($validation->failed()) {
            $reseed = $this->validator->reseed($request, [ 'name' ]);
            $payload = $builder->withValidationError($validation->messages())
                               ->withValidationReseed($reseed)
                               ->build();
            return $response->withJson($payload, 400);
        } else {
            $row = [
                'c_name' => $request->getParam('name'),
                'c_user_id' => $_SESSION['core_user_id']
            ];
            print_r($row);
            $id = $this->db->insert('t_core_domains', $row);
            if ($id)
              echo 'user was created. Id=' . $id;
            else
              echo 'insert failed: ' . $this->db->getLastError();      

            /*
            $flash = [
                "info" => 'You have been signed up',
                "info" => 'You have been signed in too'
            ];
            $payload = $builder->withFlashMessage($flash)->withCode(200)->build();
            $this->flash->addMessage('info', __('You were signed up successfully. We signed you in too!'));
            return $response->withJson($payload, 200);
            */


            return $response;

        }
    }


    public function ui_manage($request, $response)
    {
        $domains = $this->db->get("t_core_domains");
        return $this->view->render($response, 'Core/Views/domains.twig', [ 'domains' => $domains ]);
    }
    


}