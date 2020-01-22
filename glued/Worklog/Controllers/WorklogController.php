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
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
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
            ORDER BY `date` DESC, `finished` DESC
        ");
        return $log;
    }

    public function we_get(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('worklog', 1);
        $payload = $builder->withData((array)$this->we_helper())->withCode(200)->build();
        return $response->withJson($payload);
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
                                        FROM `t_worklog_items` WHERE `c_user_id` = ? ORDER BY `j_date` DESC, `j_finished` DESC", [ $id ]);
        $work = [];
        foreach ($workobj as $row) { $work[] = json_decode($row['c_json']); }
        $payload = $builder->withData((array)$work)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }

    public function migrate_jsonschema_0_1(Request $request, Response $response, array $args = []): Response {

        $loader = new \Opis\JsonSchema\Loaders\File("schema://worklog/", [
            __ROOT__ . "/glued/Worklog/Controllers/Schemas/",
        ]);
        $schema = $loader->loadSchema("schema://worklog/work.v1.schema");
        $validator = new \Opis\JsonSchema\Validator;
        $docs = $this->db->get('t_worklog_items', null, ['c_uid', 'c_json']);
        echo "<h1>Worklog/work schema migration: 0 -> 1</h1><table>";

        if ($this->db->count > 0)
            foreach ($docs as $doc) {
                // Init
                $style = "";
                $uid = (int)$doc['c_uid'];
                $doc = json_decode($doc['c_json']);
                // Data according to current schema
                echo "<tr><td width='50%'><pre>".json_encode($doc,JSON_PRETTY_PRINT);
                // Schema update
                $doc->_v = (int)$doc->_v;
                $doc->id = (int)$doc->id;
                $result = $validator->schemaValidation($doc, $schema);
                // Db write
                if ($result->isValid()) {
                    $style="background: lime;";
                    $this->db->where('c_uid', $uid);
                    $id = $this->db->update('t_worklog_items',  [ 'c_json' => json_encode($doc) ]);
                    if (!$id) { throw new HttpInternalServerErrorException( $request, 'Writing to the worklog failed on uid '.$uid); }
                } 
                // Data according to new schema
                echo "</pre></td><td width='50%' style='".$style."'><pre>".json_encode($doc,JSON_PRETTY_PRINT)."</pre></td></tr>";
        }
        echo "</table><h2>All done.</h2>";
        return $response;
    }

    public function patch(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('worklog/work', 1);

        // Get patch data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];

        // Get old data
        $this->db->where('c_uid', $req['id']);
        $doc = $this->db->getOne('t_worklog_items', ['c_json'])['c_json'];
        if(!$doc) { throw new HttpBadRequestException( $request, 'Bad worklog ID.'); }
        $doc = json_decode($doc);

        // Patch old data
        //$req['user'] = 33;
        if($doc->user != $req['user']) { throw new HttpForbiddenException( $request, 'Only own worklog items can be edited.'); }
        $doc->date = $req['date'];
        $doc->time = $req['time'];
        $doc->finished = $req['finished'];
        $doc->summary = $req['summary'];
        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain

        // Load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://worklog/", [
            __ROOT__ . "/glued/Worklog/Controllers/Schemas/",
        ]);
        $schema = $loader->loadSchema("schema://worklog/work.v1.schema");
        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->schemaValidation($doc, $schema);
        if ($result->isValid()) {
            $row = [ 'c_json' => json_encode($doc) ];
            $this->db->where('c_uid', $req['id']);
            $id = $this->db->update('t_worklog_items', $row);
            if (!$id) { throw new HttpInternalServerErrorException( $request, 'Writing to the worklog failed (update).'); }
        } else { throw new HttpBadRequestException( $request, 'Invalid worklog data.'); }

        // Success
        $payload = $builder->withData((array)$req)->withCode(200)->build();
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
                'c_domain_id' => (int)$req->domain, 
                'c_user_id' => (int)$req->user,
                'c_json' => json_encode($req)
            );
            $this->db->startTransaction(); 
            $id = $this->db->insert('t_worklog_items', $row);
            $err = $this->db->getLastErrno();
            if ($id) {
              $req->id = (int)$id; 
              $updt = $this->db->rawQuery("UPDATE `t_worklog_items` SET `c_json` = JSON_SET(c_json, '$.id', ?) WHERE c_uid = ?", Array ((int)$id, (int)$id));
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

