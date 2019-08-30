<?php

declare(strict_types=1);

namespace Tutorial\Controllers;

use App\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController extends AbstractTwigController
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
        $name = isset($args['name']) ? $args['name'] : "";
        return $this->render($response, '/Tutorial/Views/home.twig', [
            'pageTitle' => 'Glued Tutorial &mdash; Hello ' . $name,
            'name' => $name,
        ]);
    }
}
