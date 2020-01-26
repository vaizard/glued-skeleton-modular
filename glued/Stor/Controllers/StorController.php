<?php

declare(strict_types=1);

namespace Glued\Stor\Controllers;

use Carbon\Carbon;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Spatie\Browsershot\Browsershot;

class StorController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */

    public function browser(Request $request, Response $response, array $args = []): Response
    {
        // TODO add constrains on what domains a user can actually list
        $domains = $this->db->get('t_core_domains');
        
        // TODO add default domain for each user - maybe base this on some stats?
        return $this->render($response, 'Stor/Views/browser.twig', [
            'domains' => $domains
        ]);
    }


}

