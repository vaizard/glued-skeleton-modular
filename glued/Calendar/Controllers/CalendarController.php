<?php

declare(strict_types=1);

namespace Glued\Calendar\Controllers;

use Carbon\Carbon;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Spatie\Browsershot\Browsershot;

class CalendarController extends AbstractTwigController
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
}
