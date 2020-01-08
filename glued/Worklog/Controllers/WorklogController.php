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
    public function me_get(Request $request, Response $response, array $args = []): Response
    {
        $collection = $this->db->getOne('t_calendar_uris');
        $ical = json_decode((string)$collection['c_json'], true)['ical'];
   
        return $this->render($response, 'Worklog/Views/i.twig', [
            'pageTitle' => 'Worklog'
        ]);
    }

           public function converter(&$value, $key) {
                if(gettype($value) === 'boolean'){
                    $value = (bool) $value;
                }
            }

    public function me_post(Request $request, Response $response, array $args = []): Response
    {
        // start off with the request body
        $data = $request->getParsedBody();
        // add metadata
        $data['id'] = 0;
        $data['_v'] = 1;
        $data['_s'] = 'worklog/work';
        //echo $_SESSION['core_user_id'];
        // coerce types
        if ( isset($data['team']) and is_array($data['team'])) { foreach ($data['team'] as $key => $val) { $data['team'][$key] = (int)$val; } }
        if (isset($data['private'])) { $data['private'] = (bool) $data['private']; }
        print_r($data);
        // convert bodyay to object
        $data = json_decode(json_encode((object)$data));
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://worklog/", [
            __ROOT__ . "/glued/Worklog/Controllers/Schemas/",
        ]);
        $schema = $loader->loadSchema("schema://worklog/work.v1.schema");
        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->schemaValidation($data, $schema);


        print("<pre>".print_r($result,true)."</pre>");
        
        print_r($data);
        //return $response->withJson($arr);
        return $response;
    }
}
