<?php

declare(strict_types=1);

namespace Glued\Spider\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Core\Controllers\AbstractTwigController;
use Spatie\Browsershot\Browsershot;
use Respect\Validation\Validator as v;

class SpiderController extends AbstractTwigController
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
        $uri = isset($args['uri']) ? $args['uri'] : "";
        $validation = v::url()->validate($uri);
        $img = Browsershot::url('https://industra.space')->fullPage()->screenshot();
        $base64img = base64_encode($img);
        Browsershot::url('https://industra.space')->fullPage()->save('/var/www/html/glued-skeleton/private/data/sp.png');
        return $this->render($response, 'Spider/Views/browse.twig', [
            'pageTitle' => 'Spider', 'uri' => $uri, 'validation' => '$validation', 'screenshot' => "data:image/png;base64," . $base64img
        ]);
    }
}
