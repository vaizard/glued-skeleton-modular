<?php

declare(strict_types=1);

namespace Glued\Worklog\Controllers;

use Carbon\Carbon;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpForbiddenException;
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

    public function me_ui(Request $request, Response $response, array $args = []): Response
    {
        // TODO add constrains on what domains a user can actually list
        $domains = $this->db->get('t_core_domains');
        
        // TODO add default domain for each user - maybe base this on some stats?
        return $this->render($response, 'Worklog/Views/i.twig', [
            'domains' => $domains
        ]);
    }

    /**
     * Transitional helper function to circument ajax loaded twig.js->datatables chaining.
     * Until we resolve this, we won't have we_ui() ask data from we_get() on client side.
     * we_ui() now temporarily generates the whole dataset via server side twig templating
     * and getting data from this helper function.
     * 
     * @return object team worklog
     */
    private function we_helper() {
        $log = $this->db->rawQuery("
            SELECT
                c_domain_id as 'domain',
                t_worklog_items.c_user_id as 'user',
                t_core_users.c_name as 'user_name',
                t_core_domains.c_name as 'domain_name',
                c_json->>'$._s' as '_s',
                c_json->>'$._v' as '_v',
                c_json->>'$.summary' as 'summary',
                c_json->>'$.date' as 'date',
                c_json->>'$.time' as 'time',
                c_json->>'$.location' as 'location',
                c_json->>'$.status' as 'status',
                c_json->>'$.private' as 'private',
                c_json->>'$.finished' as 'finished',
                c_json->>'$.project' as 'project'
            FROM `t_worklog_items` 
            LEFT JOIN t_core_users ON t_worklog_items.c_user_id = t_core_users.c_uid
            LEFT JOIN t_core_domains ON t_worklog_items.c_domain_id = t_core_domains.c_uid
        ");
        return $log;
    }

    public function we_get(Request $request, Response $response, array $args = []): Response
    {

        $log = $this->we_helper();
        return $response->withJson($log);
        // TODO handle errors
    }

    public function we_ui(Request $request, Response $response, array $args = []): Response
    {
        // TODO remove datatables hack and fetch data via api 
        // (see $this->we_helper() description) for info about the workaround.
        return $this->render($response, 'Worklog/Views/we.twig', [ 'log' =>  $this->we_helper()]);
    }


    public function me_get(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('worklog/work', 1);
        $id = (int)$_SESSION['core_user_id'] ?? 0;
        if ($id === 0) { throw new HttpForbiddenException( $request, 'Please log in.' );  }
        $workobj = $this->db->rawQuery("SELECT *, JSON_EXTRACT(c_json, '$.date') AS j_date, JSON_EXTRACT(c_json, '$.finished') AS j_finished 
                                        FROM `t_worklog_items` WHERE `c_user_id` = ? ORDER BY `j_date`, `j_finished` ASC", [ $id ]);
        $work = [];
        foreach ($workobj as $row) { $work[] = json_decode($row['c_json']); }
        $payload = $builder->withData((array)$work)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }



    public function me_post(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('worklog/work', 1);
        // start off with the request body & add data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        // TODO document that the validator will set default data if defaults in the schema
        // coerce types
        
        //if ( isset($req['domain']) and is_array($req['domain'])) { foreach ($req['domain'] as $key => $val) { $req['domain'][$key] = (int)$val; } }
        // TODO check again if user is member of a domain that was submitted
        if ( isset($req['domain']) ) { $req['domain'] = (int) $req['domain']; }
        if ( isset($req['private']) ) { $req['private'] = (bool) $req['private']; }
        // convert bodyay to object
        $req = json_decode(json_encode((object)$req));
        // print("<pre>".print_r($req,true)."</pre>"); // DEBUG
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://worklog/", [
            __ROOT__ . "/glued/Worklog/Controllers/Schemas/",
        ]);
        $schema = $loader->loadSchema("schema://worklog/work.v1.schema");
        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_domain_id' => $req->domain, 
                'c_user_id' => $req->user,
                'c_json' => json_encode($req)
            );
            $this->db->startTransaction(); 
            $id = $this->db->insert('t_worklog_items', $row);
            $err = $this->db->getLastErrno();
            if ($id) {
              $req->id = $id; 
              $row = [ "c_json" => "JSON_SET( c_json, '$.id', ".$id.")" ];
              $updt = $this->db->rawQuery("UPDATE `t_worklog_items` SET `c_json` = JSON_SET(c_json, '$.id', ?) WHERE c_uid = ?", Array (strval($id), (int)$id));
              $err += $this->db->getLastErrno();
            }
            if ($err >= 0) { $this->db->commit(); } else { $this->db->rollback(); throw new HttpInternalServerErrorException($request, __('Database error')); }
            $payload = $builder->withData((array)$req)->withCode(200)->build();
            return $response->withJson($payload, 200);
        } else {
            $reseed = $request->getParsedBody();
            $payload = $builder->withValidationReseed($reseed)
                               //->withValidationError($array)
                               ->withCode(400)
                               ->build();
            return $response->withJson($payload, 400);
        }

    }

public function me_put(Request $request, Response $response, array $args = []): Response {
    // TODO when updating the json, make sure to update c_domain_id, c_user_id
}

}

