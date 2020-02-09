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

    private $builder;
    private $validator;

    public function __construct() {
        $this->builder = new JsonResponseBuilder('calendar/sources', 1);
        $this->$validator = new \Opis\JsonSchema\Validator;
    }
    
    public function calendars_fetch(Request $request, Response $response, array $args = []): Response
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

        //echo $min_start;
        //echo $max_end;
        
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
            'pageTitle' => 'Calendar', 'out' => $out
        ]);
    }

    public function events_get(Request $request, Response $response, array $args = []): Response {
        return $this->render($response, 'Calendar/Views/list.twig', [
            'pageTitle' => 'Calendar', 'out' => $out
        ]);
    }

    public function calendars_list_ui(Request $request, Response $response, array $args = []): Response {
        $out = [];

        // TODO - write a core function that will get domains for a given user so that we dont copy paste tons of code around (once the oneliner below gets properly expanded)
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');


        return $this->render($response, 'Calendar/Views/list.twig', [
            'pageTitle' => 'Calendar', 'out' => $out, 'domains' => $domains
        ]);
    }

    public function calendars_post(Request $request, Response $response, array $args = []): Response {

        // start off with the request body & add data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        
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
        $schema = $loader->loadSchema("schema://calendar/calendar.v1.schema");
        $result = $this->validator->schemaValidation($req, $schema);
// stophere
//
/*    "_s",
    "_v",
    "id",
    "user",
    "uri",
    "name",
    "driver"*/
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

    public function calendars_read(Request $request, Response $response, array $args = []): Response {
        return $this->render($response, 'Calendar/Views/list.twig', [
            'pageTitle' => 'Calendar', 'out' => $out
        ]);
    }

}
