<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class AbstractJsonController extends AbstractController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */

    public $API_NAME = '';
    public $API_VERSION = '';

    public function api_meta($res_code) {
        $res_name[200] = 'success';
        $res_name[500] = 'internal server error';
        if (!array_key_exists($res_code, $res_name)) { $res_name[$res_code] = "unknown"; }

        $payload = array(
             'api' => $this->API_NAME, //self::API_NAME,
             'version' => $this->API_VERSION, //self::API_VERSION,
             'response' => array (
                 'timestamp' => time(),
                 'status' => $res_code,
                 'message' => $res_name[$res_code],
                 'id' => uniqid(),),
             'pagination' => [],
             'embeds' => [],
        );
        return $payload;
    }



}
