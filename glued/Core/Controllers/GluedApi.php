<?php

declare(strict_types=1);

namespace Glued\Core\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GluedApi extends AbstractJsonController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    

    public $API_NAME = 'glued';
    public $API_VERSION  = 1;

    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $payload = $this->api_meta(200);
        $payload['data']['endpoints']['core/profiles'] = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost() . $this->routerParser->urlFor('core.profiles.list.api01');
        return $response->withJson($payload);
    }
}

