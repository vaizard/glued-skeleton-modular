<?php

declare(strict_types=1);

namespace Glued\Calendar\Controllers;

use Carbon\Carbon;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpNotFoundException;


class CalendarController extends AbstractTwigController
{

    // ==========================================================
    // HELPERS
    // ==========================================================

    private function sql_insert_with_json($table, $row) {
        $this->db->startTransaction(); 
        $id = $this->db->insert($table, $row);
        $err = $this->db->getLastErrno();
        if ($id) {
          $updt = $this->db->rawQuery("UPDATE `".$table."` SET `c_json` = JSON_SET(c_json, '$.id', ?) WHERE c_uid = ?", Array ((int)$id, (int)$id));
          $err += $this->db->getLastErrno();
        }
        if ($err >= 0) { $this->db->commit(); } else { $this->db->rollback(); throw new HttpInternalServerErrorException($request, __('Database error')." ".$err); }
        return (int)$id;
    }

    // ==========================================================
    // EVENTS
    // ==========================================================

    public function events_fetch(Request $request, Response $response, array $args = []): Response
    {
        $collection = $this->db->get('t_calendar_uris');
        if (!$collection) {
            return false;
        }

        $ical = json_decode($collection['c_json'], true)['ical'];
        $calendar = VObject\Reader::read(
            fopen($ical,'r')
        );

        $min_start = false;
        $max_end = false;
        $carb_now = Carbon::now();

        foreach($calendar->vevent as $event) {
            $uid = (string)$event->created.(string)$event->uid;
            $dtend = (string)$event->dtend;
            if (empty($dtend)) { $dtend = (string)$event->dtstart; }
            $carb_created = Carbon::createFromFormat('Ymd\THis\Z', (string)$event->created);


            $events[$uid]['uid'] = (string)$event->uid;
            $events[$uid]['dtstart'] = (string)$event->dtstart;
            $events[$uid]['start'] = strtotime((string)$event->dtstart);
            $events[$uid]['dtend'] = $dtend; 
            $events[$uid]['end'] = strtotime($dtend);
            $events[$uid]['last_modified'] = strtotime((string)$event->last_modified);
            $events[$uid]['created'] = (string)$event->created;
            $events[$uid]['hrcreated'] = $carb_created->diffForHumans($carb_now);
            $events[$uid]['description'] = (string)$event->description;
            $events[$uid]['summary'] = (string)$event->summary;
            if ( ($min_start === false) or $events[$uid]['start'] < $min_start ) { $min_start = $events[$uid]['start'] ; }
            if ( ($max_end === false) or $events[$uid]['end'] > $max_end ) { $max_end = $events[$uid]['end'] ; }
        }
        
        $period = new \DatePeriod(
            new \DateTime(date(DATE_ATOM, $min_start)),
            new \DateInterval('P1D'),
            new \DateTime(date(DATE_ATOM, $max_end))
        );

       foreach ($period as $key => $value) {
            $date = (string)$value->format('Y-m-d');
            //echo "<br>".$date;
            //$out[$date][] = [];

            foreach ($events as $uid => $event) {
                if (($date >= date('Y-m-d', $event['start'])) && ($date <= date('Y-m-d', $event['end']))) {
                    $out[$date][$uid] = $event;
                    //echo "<br>"."------ ".$event['start'].' ... '.$event['end'].': '.$event['summary'];
                }
            }


        }

//print("<pre>".print_r($out,true)."</pre>");
  //      return $response;

        return $this->render($response, 'Calendar/Views/browse.twig', [
            'pageTitle' => 'Calendar', 'out' => $out
        ]);
    }


    public function events_list_ui(Request $request, Response $response, array $args = []): Response {
        return $this->render($response, 'Calendar/Views/list.twig', [
            'pageTitle' => 'Calendar'
        ]);
    }

    public function events_get(Request $request, Response $response, array $args = []): Response {
        return $this->render($response, 'Calendar/Views/list.twig', [
            'pageTitle' => 'Calendar', 'out' => $out
        ]);
    }

    // ==========================================================
    // SOURCES
    // ==========================================================

    private function sql_sources_list() {
        $data = $this->db->rawQuery("
            SELECT
                c_domain_id as 'domain',
                t_calendar_sources.c_user_id as 'user',
                t_core_users.c_name as 'user_name',
                t_core_domains.c_name as 'domain_name',
                c_json->>'$.id' as 'id',
                c_json->>'$._s' as '_s',
                c_json->>'$._v' as '_v',
                c_json->>'$.uri' as 'uri',
                c_json->>'$.name' as 'name',
                c_json->>'$.driver' as 'driver'
            FROM `t_calendar_sources` 
            LEFT JOIN t_core_users ON t_calendar_sources.c_user_id = t_core_users.c_uid
            LEFT JOIN t_core_domains ON t_calendar_sources.c_domain_id = t_core_domains.c_uid
        ");
        return $data;
    }

    public function sources_list_ui(Request $request, Response $response, array $args = []): Response {
        // TODO - write a core function that will get domains for a given user so that we dont copy paste tons of code around (once the oneliner below gets properly expanded)
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Calendar/Views/sources.twig', [
            'domains' => $domains
        ]);
    }

    // ==========================================================
    // SOURCES API
    // ==========================================================

    public function sources_list(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('calendar.sources', 1);
        $payload = $builder->withData((array)$this->sql_sources_list())->withCode(200)->build();
        return $response->withJson($payload);
        // TODO handle errors
    }

    public function sources_patch(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('calendar.sources', 1);

        // Get patch data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];
        
        // Get old data
        $this->db->where('c_uid', $req['id']);
        $doc = $this->db->getOne('t_calendar_sources', ['c_json'])['c_json'];
        if (!$doc) { throw new HttpBadRequestException( $request, __('Bad source ID.')); }
        $doc = json_decode($doc);

        // TODO replace this lame acl with something propper.
        if($doc->user != $req['user']) { throw new HttpForbiddenException( $request, 'Only own worklog items can be edited.'); }

        // Patch old data
        $doc->uri = $req['uri'];
        $doc->name = $req['name'];
        $doc->domain = $req['domain'];
        $doc->driver = $req['driver'];
        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain

        // load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://calendar/", [
            __ROOT__ . "/glued/Calendar/Controllers/Schemas/",
        ]);
        $validator = new \Opis\JsonSchema\Validator;
        $schema = $loader->loadSchema("schema://calendar/calendar.v1.schema");
        $result = $validator->schemaValidation($doc, $schema);

        if ($result->isValid()) {
            $row = [ 'c_json' => json_encode($doc) ];
            $this->db->where('c_uid', $req['id']);
            $id = $this->db->update('t_calendar_sources', $row);
            if (!$id) { throw new HttpInternalServerErrorException( $request, __('Updating the calendar source failed.')); }
        } else { throw new HttpBadRequestException( $request, __('Invalid calendar source data.')); }

        // Success
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);  
    }

    public function sources_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('calendar.sources', 1);
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = 0;
         
        // TODO check again if user is member of a domain that was submitted
        if ( isset($req['domain']) ) { $req['domain'] = (int) $req['domain']; }
        if ( isset($req['private']) ) { $req['private'] = (bool) $req['private']; }
        // convert bodyay to object
        $req = json_decode(json_encode((object)$req));
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new \Opis\JsonSchema\Loaders\File("schema://calendar/", [
            __ROOT__ . "/glued/Calendar/Controllers/Schemas/",
        ]);
        $validator = new \Opis\JsonSchema\Validator;
        $schema = $loader->loadSchema("schema://calendar/calendar.v1.schema");
        $result = $validator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_domain_id' => (int)$req->domain, 
                'c_user_id' => (int)$req->user,
                'c_json' => json_encode($req)
            );
            $req->id = $this->sql_insert_with_json('t_calendar_sources', $row);
            $payload = $builder->withData((array)$req)->withCode(200)->build();
            return $response->withJson($payload, 200);
        } else {
            $reseed = $request->getParsedBody();
            $payload = $builder->withValidationReseed($reseed)
                               ->withValidationError($result->getErrors())
                               ->withCode(400)
                               ->build();
            return $response->withJson($payload, 400);
        }
    }

    public function sources_delete(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('calendar.sources', 1);
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }

}
