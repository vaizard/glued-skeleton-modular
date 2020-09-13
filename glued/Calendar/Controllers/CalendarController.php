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
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;
use \Opis\JsonSchema\Loaders\File as JSL;

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
        if ($err === 0) { $this->db->commit(); } else { $this->db->rollback(); throw new \Exception(__('Database error: ')." ".$err." ".$this->db->getLastError()); }
        return (int)$id;
    }

    // ==========================================================
    // EVENTS
    // ==========================================================

    public function sources_sync(Request $request, Response $response, array $args = []): Response
    {

        // TODO add rbac here
        $this->db->where('c_uid', (int)$args['uid']);
        $source = $this->db->getOne('t_calendar_sources');
        if (!$source) {
            $builder = new JsonResponseBuilder('calendar.sources.sync', 1);
            $payload = $builder->withCode(404)->build();
            return $response->withJson($payload);
        }

        $uri = json_decode($source['c_json'], true)['uri'];
        $calendar = VObject\Reader::read( fopen( $uri, 'r' ) );

        $min_start = false;
        $max_end = false;
        $carb_now = Carbon::now();
        $table = 't_calendar_objects';

 /*
        $i = 1;
        foreach($calendar->vevent as $event) {
            print_r($event->serialize()); 
            echo '<br><br>';
            $i++;
        }
*/

        //print_r((array)$calendar->vevent[1]);
        //echo $calendar->serialize();
    //    return $response;    
        foreach($calendar->vevent as $event) {

            // pregen json
            $object = null;
            unset($event->DTSTAMP); // current grabbing timestamp. keeping this would mean you cant hash-compare

            $json['uid'] = (string)$event->uid;
            $json['recurrence-id'] = (string)$event->recurrence_id;
            $json['created'] = (string)$event->created;
            $json['summary'] = (string)$event->summary;
            $json['description'] = (string)$event->description;
            $json['last-modified'] = strtotime((string)$event->last_modified);
            $json['dstart'] = (string)$event->dtstart;
            $json['dtend'] = (string)$event->dtend;
            $json['rrule'] = json_encode($event->rrule);
            $json['rdate'] = json_encode($event->rdate);
            $json['exrule'] = json_encode($event->exrule);
            $json['exdate'] = json_encode($event->exdate);
            $json['location'] = (string)$event->location;
            $json['sequence'] = (string)$event->sequence;
            $json['attach'] = (array)($event->attach);
            if (empty($json['dtend'])) { $json['dtend'] = (string)$event->dtstart; }

            echo "<br>------------------------------";
            echo (string)$event->uid;
            echo " ";
            echo $event->attach;

            if ((string)$event->uid == "857pobo94q3377f4u2foct4024@google.com") { echo(json_encode( $event->attach )); }
            // tady udelej test na to jestli je rrul, rdate, exrule, exdate objekt
            // pak prijde recurreccneid do unikatniho identifikatoru, nastavuj ho asi vzdy? nebo jen kdyz je nastavene?

            //if is_object($event->attach) {
            //foreach ($event->attach as $x) { echo $x; }
            //}
            // pregen row to compare against database and eventually upsert
            $row['c_object'] = (string)$event->serialize();
            $row['c_object_hash'] = hash('sha256', $row['c_object']);
            $row['c_json'] = json_encode($json);
            $rev[time()] = $row['c_object'];

            // get object from db, if we already habe it
            $this->db->where('c_source_id', (int)$args['uid']);
            $this->db->where('c_object_uid', (string)$event->uid);
            $object = $this->db->getOne('t_calendar_objects');

            if ($object) {
                // uptdate branch
                if ($row['c_object_hash'] != $object['c_object_hash']) {
                    // there is stuff to update
                    // dtstamp!!!
                    echo "<br><br>";
                    echo $row['c_object_hash']."<br>";
                    echo $object['c_object_hash']."<br>OBJECT OLD START<br>";
                    echo $row['c_object']."<br>OBJECT OLD END<br>OBJECT NEW START<br>";
                    echo $object['c_object']."<br>OBJECT NEW END";
                    if ($row['c_json'] == $object['c_json']) { echo "same"; }
                    $row['c_revision_counter'] = $object['c_revision_counter'] + 1;
                    //$row['c_revisions'] = json_encode($rev); // todo, zde pridej revizi
                    print_r('<br/>UPDTATING... <br>New value is:<br>'. $row['c_json'].'<br>old is:<br>'.$object['c_json']);
                } else {
                    // do nothing, we got current data in db already
                    //print_r('<br/>KEEP '. $row['c_json']);
                }
            } else {
                // create branch
                    // default values
                    $row['c_attr'] = '{}';
                    $row['c_revisions'] = json_encode($rev);
                    $row['c_revision_counter'] = 1;
                    $row['c_source_id'] = (int)$args['uid'];
                    $row['c_object_uid'] = $json['uid'];
                    print_r('<br/>INST '. $row['c_json']);
                    try { $row_ins = $this->sql_insert_with_json($table, $row); } catch (Exception $e) { 
                        throw new HttpInternalServerErrorException($request, $e->getMessage());  
                    }

            }

            // TODO check for deleted events


            

            
            /*
            


*/
/*
            $uid = (string)$event->created.(string)$event->uid;
            $carb_created = Carbon::createFromFormat('Ymd\THis\Z', (string)$event->created);
            $events[$uid]['start'] = strtotime((string)$event->dtstart);
            $events[$uid]['end'] = strtotime($dtend);
            $events[$uid]['created'] = (string)$event->created;
            $events[$uid]['hrcreated'] = $carb_created->diffForHumans($carb_now);

            if ( ($min_start === false) or $events[$uid]['start'] < $min_start ) { $min_start = $events[$uid]['start'] ; }
            if ( ($max_end === false) or $events[$uid]['end'] > $max_end ) { $max_end = $events[$uid]['end'] ; }
        }
        
        $period = new \DatePeriod(
            new \DateTime(date(DATE_ATOM, $min_start)),
            new \DateInterval('P1D'),
            new \DateTime(date(DATE_ATOM, $max_end))
        );
*/
        }
        return $response;
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
    // SOURCES UI
    // ==========================================================

    public function sources_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');

        return $this->render($response, 'Calendar/Views/sources.twig', [
            'domains' => $domains
        ]);
    }

    // ==========================================================
    // SOURCES API
    // ==========================================================

    private function sql_sources_list() {
        $data = $this->db->rawQuery("
            SELECT
                c_domain_id as 'domain',
                t_calendar_sources.c_user_id as 'user',
                t_core_users.c_name as 'user_name',
                t_core_domains.c_name as 'domain_name',
                t_calendar_sources.c_json->>'$.id' as 'id',
                t_calendar_sources.c_json->>'$._s' as '_s',
                t_calendar_sources.c_json->>'$._v' as '_v',
                t_calendar_sources.c_json->>'$.uri' as 'uri',
                t_calendar_sources.c_json->>'$.name' as 'name',
                t_calendar_sources.c_json->>'$.driver' as 'driver'
            FROM `t_calendar_sources` 
            LEFT JOIN t_core_users ON t_calendar_sources.c_user_id = t_core_users.c_uid
            LEFT JOIN t_core_domains ON t_calendar_sources.c_domain_id = t_core_domains.c_uid
        ");
        return $data;
    }


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
        if($doc->user != $req['user']) { throw new HttpForbiddenException( $request, 'You can only edit your own calendar sources.'); }

        // Patch old data
        $doc->uri = $req['uri'];
        $doc->name = $req['name'];
        $doc->domain = (int)$req['domain'];
        $doc->driver = $req['driver'];
        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain

        // load the json schema and validate data against it
        $loader = new JSL("schema://calendar/", [ __ROOT__ . "/glued/Calendar/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://calendar/calendar.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($doc, $schema);

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
        // convert body to object
        $req = json_decode(json_encode((object)$req));
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new JSL("schema://calendar/", [ __ROOT__ . "/glued/Calendar/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://calendar/calendar.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_domain_id' => (int)$req->domain, 
                'c_user_id' => (int)$req->user,
                'c_json' => json_encode($req),
                'c_attr' => '{}'
            );
            try { $req->id = $this->sql_insert_with_json('t_calendar_sources', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
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
