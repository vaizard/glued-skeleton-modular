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
        // start off with the request body & add data
        $data = $request->getParsedBody();
        $data['user'] = (int)$_SESSION['core_user_id'];
        // TODO document that the validator will set default data if defaults in the schema
        // coerce types
        if ( isset($data['team']) and is_array($data['team'])) { foreach ($data['team'] as $key => $val) { $data['team'][$key] = (int)$val; } }
        if ( isset($data['private']) ) { $data['private'] = (bool) $data['private']; }
        // convert bodyay to object
        $data = json_decode(json_encode((object)$data));
        print("<pre>".print_r($data,true)."</pre>");
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://worklog/", [
            __ROOT__ . "/glued/Worklog/Controllers/Schemas/",
        ]);
        $schema = $loader->loadSchema("schema://worklog/work.v1.schema");
        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->schemaValidation($data, $schema);

        if ($result->isValid()) {
            print('JSON is valid');
        } else {
            $error = $result->getFirstError();
            print_r($error);
        }



        
        print_r(json_encode($data));
        //return $response->withJson($arr);
        return $response;
    }
}
