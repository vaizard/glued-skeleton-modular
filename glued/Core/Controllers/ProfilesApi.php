<?php

declare(strict_types=1);

namespace Glued\Core\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Core\Classes\Users;

class ProfilesApi extends AbstractJsonController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */

    public $API_NAME = 'profiles';
    public $API_VERSION  = 1;

    public function list(Request $request, Response $response, array $args = []): Response
    {
        // DO RBAC HERE

        $collection = $this->db->get('t_core_profiles');

        if ($this->db->getLastErrno() === 0) { $res_code = 200; } else {  $res_code = 500; }
        $arr = $this->api_meta($res_code);

        if (($this->db->getLastErrno() === 0) and ($this->db->count > 0)) {
            foreach ($collection as $object) { 
                $arr['data'][] = json_decode($object['c_json'], true);
            }
        }

        // DEBUG CODE
        // $payload = json_encode($arr);
        // $response->getBody()->write($payload);
        // return $response->withHeader('Content-type', 'application/json;charset=utf-8');

        return $response->withJson($arr);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    { 
        return $response->withJson(['works create' => true]);
    }

}





// Prefill data:
// 



//      url: "https://'.$this->container['settings']['glued']['hostname'].$this->container->router->pathFor('assets.api.new').'",


        //$this->container['settings']['glued']['hostname']
        //$this->container->settings->glued->hostname
        // vnitrek onsubmit funkce
        //         alert('xhr status: ' + xhr.status + ', status: ' + status + ', err: ' + err)
