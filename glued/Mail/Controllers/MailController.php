<?php

declare(strict_types=1);

namespace Glued\Mail\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Core\Controllers\AbstractTwigController;
use Respect\Validation\Validator as v;

class MailController extends AbstractTwigController
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
        return $this->render($response, 'Mail/Views/opera.twig', [
            'pageTitle' => 'Opera',
        ]);
    }
}
