<?php

declare(strict_types=1);

namespace Tutorial\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BasicController extends AbstractController
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
        return $this->render($response, 'home.twig', [
            'pageTitle' => 'Home',
        ]);
    }
}
