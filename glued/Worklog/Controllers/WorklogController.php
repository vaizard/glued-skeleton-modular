<?php

declare(strict_types=1);

namespace Glued\Worklog\Controllers;

use Carbon\Carbon;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Spatie\Browsershot\Browsershot;

class WorklogController extends AbstractTwigController
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
        $collection = $this->db->getOne('t_calendar_uris');
        $ical = json_decode($collection['c_json'], true)['ical'];
   
        return $this->render($response, 'Worklog/Views/i.twig', [
            'pageTitle' => 'Worklog'
        ]);
    }
}
