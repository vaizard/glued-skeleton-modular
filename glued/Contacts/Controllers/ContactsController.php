<?php

declare(strict_types=1);

namespace Glued\Contacts\Controllers;

use Carbon\Carbon;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpForbiddenException;
use Defr\Ares;
// grabbing
use Symfony\Component\DomCrawler\Crawler;

class ContactsController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */

/*

    private $client;
    public function __construct(Client $client)
    {
        $this->client = $client; // Goutte\Client
    }
*/
    public function cz_ares_ids(Request $request, Response $response, array $args = []): Response {
      $ares = new Ares();
      $record = $ares->findByIdentificationNumber($args['id']); 
      $data['name'] = $record->getCompanyName();
      $data['street'] = $record->getStreet();
      $data['zip'] = $record->getZip();
      $data['city'] = $record->getTown();
      $data['id'] = $record->getCompanyId();
      $data['taxid'] = $record->getTaxId();
      //$json = json_encode($data);
      //print_r($json);
      //return $response;
      $builder = new JsonResponseBuilder('contacts.search', 1);
      $payload = $builder->withData((array)$data)->withCode(200)->build();
      return $response->withJson($payload);

    }

    public function cz_names(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('contacts.search', 1);
        $search_string = $args['name'];
        
      if (strlen($search_string) < 3) {
         $payload = $builder->withMessage('Please use at least 3 characters for your search.')->withCode(200)->build();
         return $response->withJson($payload);
      }
        
        $result = [];
        
          $uri = 'https://or.justice.cz/ias/ui/rejstrik-$firma?jenPlatne=PLATNE&nazev='.$search_string.'&polozek=500';
          $crawler = $this->goutte->request('GET', $uri);
          $crawler->filter('div.search-results > ol > li.result')->each(function (Crawler $table) use (&$result) {
            $r['org'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(2) > strong')->text();
            $r['regid'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(4) > strong')->text();
            $r['adr'] = $table->filter('div > table > tbody > tr:nth-child(3) > td:nth-child(2)')->text();
            $r['regby'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(2)')->text();
            $r['regdt'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(4)')->text();
            $result[] = $r;
            //vatid
            //https://adisreg.mfcr.cz/adistc/DphReg?id=1&pocet=1&fu=&OK=+Search+&ZPRAC=RDPHI1&dic=29228107
          });
        
      $payload = $builder->withData((array)$result)->withCode(200)->build();
      return $response->withJson($payload);
      //print("<pre>".print_r($result,true)."</pre>");
      //return $response;
    }

    public function cz_ids(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('contacts.search', 1);
      $id = $args['id'];
      if (strlen($id) != 8) {
         $payload = $builder->withMessage('Czech company IDs are 8 numbers in total.')->withCode(200)->build();
         return $response->withJson($payload);
      }


      $result = [];

      $uri = 'https://or.justice.cz/ias/ui/rejstrik-$firma?jenPlatne=PLATNE&ico='.$id.'&polozek=500';
      $crawler = $this->goutte->request('GET', $uri);
      $crawler->filter('div.search-results > ol > li.result')->each(function (Crawler $table) use (&$result, &$id) {
        $r['adr'][0]['type'] = 'main';
        $r['org'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(2) > strong')->text();
        $r['regid'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(4) > strong')->text();
        $r['adr'][0]['unstructured'] = $table->filter('div > table > tbody > tr:nth-child(3) > td:nth-child(2)')->text();
        $r['regby'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(2)')->text();
        $r['regdt'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(4)')->text();
 
        $m_in = [
            'ledna', 'února', 'března', 'dubna', 'května',
            'června', 'července', 'srpna', 'září', 'října',
            'listopadu', 'prosince'
        ];

        $m_out = [
            'leden', 'únor', 'březen', 'duben', 'květen',
            'červen', 'červenec', 'srpen', 'září', 'říjen',
            'listopad', 'prosinec'
        ];

        $m_out = [
            'January', 'February', 'March', 'April', 'May',
            'June', 'July', 'August', 'September', 'October',
            'November', 'December'
        ];

        $date = str_replace($m_in, $m_out, $r['regdt']);
        $date = str_replace(".", "", $date);
        $r['xxx'] = strtotime($date);
        //$r['xxx'] = $date->isoFormat('LLLL');;

        $client = new \SoapClient("http://adisrws.mfcr.cz/adistc/axis2/services/rozhraniCRPDPH.rozhraniCRPDPHSOAP?wsdl", array('trace' => true));

        $arr = json_decode(
          json_encode(
            $client->__call("getStatusNespolehlivyPlatceRozsireny", array(0 => array($id)))
          ), true
        );

        if (($arr['status']['statusCode'] === 0) and strcasecmp($arr['statusPlatceDPH']['nazevSubjektu'], $r['org'])) { 
          //echo '<br>'.$arr['statusPlatceDPH']['nespolehlivyPlatce'];

          $r['adr'][0]['street'] = $arr['statusPlatceDPH']['adresa']['uliceCislo'];
          $r['adr'][0]['locacity'] = $arr['statusPlatceDPH']['adresa']['mesto'];
          $r['adr'][0]['zip'] = $arr['statusPlatceDPH']['adresa']['psc'];
          $r['adr'][0]['country'] = $arr['statusPlatceDPH']['adresa']['stat'];

          $a = 0;
          foreach ($arr['statusPlatceDPH']['zverejneneUcty']['ucet'] as $ucet) {
            $acc[$a]['number'] = $ucet['standardniUcet']['cislo'];
            $acc[$a]['bank-code'] = $ucet['standardniUcet']['kodBanky'];
            $acc[$a]['country'] = 'CZ';
            $acc[$a]['meta'][0]['source'] = 'adisrws.mfcr.cz';
            $acc[$a]['meta'][0]['date-published'] = $ucet['datumZverejneni'];
            $a++;
          }

          $r['acc'] = $acc;
        }
        $result[] = $r;
      });

      $payload = $builder->withData((array)$result)->withCode(200)->build();
      return $response->withJson($payload);
      //print("<pre>".print_r($result,true)."</pre>");
      //return $response;
    }



    public function cz_ares_names2(Request $request, Response $response, array $args = []): Response {
      $name = $args['name'];
      $builder = new JsonResponseBuilder('contacts.search', 1);

      if (strlen($name) < 3) {
         $payload = $builder->withMessage('Please use at least 3 characters for your search.')->withCode(200)->build();
         return $response->withJson($payload);
      }

      $uri = 'http://wwwinfo.mfcr.cz/cgi-bin/ares/ares_es.cgi?obch_jm='.urlencode($name).'&filtr=0';
      $file = @file_get_contents($uri);
      if($file) {
          $xml = @simplexml_load_string($file);
      }
       
      if($xml) {
          $ns = $xml->getDocNamespaces();
          $data = $xml->children($ns['are']);
          $dtt = $data->children($ns['dtt'])->V;
      }
      else {
          $ares_stav_fin  = __('The Czech ARES database is offline');
      }

      //$records = json_decode(json_encode($dtt))->S;
      $records = json_decode(json_encode($dtt));
      $i = 0;
      print_r($records);
      return $response;

      foreach ($records as $record) {
        $out[$i]['id'] = $record->ico;
        $out[$i]['name'] = $record->ojm;
        $out[$i]['address'] = $record->jmn;
        $i++;
      }
      print_r($out);
      return $response;
      //die();
      //$json = json_encode($out);
      //print_r($json);
      //print("<pre>".print_r($out,true)."</pre>");
      //return $response;
      //$newresponse = $response->withJson($out)->withHeader('Content-type', 'application/json');
      //return $newresponse;
        //$builder = new JsonResponseBuilder('contacts.search', 1);
        //$payload = $builder->withData((array)$out)->withCode(200)->build();
        //return $response->withJson($payload);
  }

    public function collection_ui(Request $request, Response $response, array $args = []): Response
    {
      $uribase = strtolower(parse_url((string)$request->getUri(), PHP_URL_SCHEME)).'://'.strtolower(parse_url((string)$request->getUri(), PHP_URL_HOST));

      $jsf_schema   = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.schema');
      $jsf_uischema = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.formui');
      #$jsf_uischema = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/test.v1.formui');
      $jsf_formdata = '{"data":{"ts_created":"'.time().'","ts_updated":"'.time().'"}}';
      $jsf_onsubmit = '
        $.ajax({
          url: "'.$uribase.$this->routerParser->urlFor('contacts.items.api01').'",
          dataType: "text",
          type: "POST",
          data: "stockdata=" + JSON.stringify(formData.formData),
          success: function(data) {
            // diky replacu nezustava puvodni adresa v historii, takze se to vice blizi redirectu
            // presmerovani na editacni stranku se vraci z toho ajaxu
            window.location.replace(data);
            /*
            ReactDOM.render((<div><h1>Thank you</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre></div>), 
                     document.getElementById("main"));
            */
          },
          error: function(xhr, status, err) {
            ReactDOM.render((<div><h1>Something goes wrong ! not saving.</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre></div>), 
                     document.getElementById("main"));
          }
        });
      ';

        // TODO add constrains on what domains a user can actually list
        //$domains = $this->db->get('t_core_domains');
        
        // TODO add default domain for each user - maybe base this on some stats?
        return $this->render($response, 'Contacts/Views/collection.twig', [
            //'domains' => $domains
            'json_schema_output' => $jsf_schema,
            'json_uischema_output' => $jsf_uischema,
            'json_formdata_output' => $jsf_formdata,
            'json_onsubmit_output' => $jsf_onsubmit
        ]);
    }


    // show form for add new contact
    public function addContactForm($request, $response)
    {
        $form_output = '';
        $jsf_schema   = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.schema');
        $jsf_uischema = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.formui');
        $jsf_formdata = '{"data":{}}';
        $jsf_onsubmit = '
            $.ajax({
                url: "https://'.$this->settings['glued']['hostname'].$this->routerParser->pathFor('contacts.api.new').'",
                dataType: "text",
                type: "POST",
                data: "billdata=" + JSON.stringify(formData.formData),
                success: function(data) {
                    ReactDOM.render((<div><h1>Thank you</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre><h2>Final data</h2><pre>{data}</pre></div>), 
                         document.getElementById("main"));
                },
                error: function(xhr, status, err) {
                    alert(status + err + data);
                    ReactDOM.render((<div><h1>Something goes wrong ! not saving.</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre></div>), 
                         document.getElementById("main"));
                }
            });
        ';

        return $this->render($response, 'Core/Views/glued.twig', [
            'json_schema_output' => $jsf_schema,
            'json_uischema_output' => $jsf_uischema,
            'json_formdata_output' => $jsf_formdata,
            'json_onsubmit_output' => $jsf_onsubmit,
            'json_custom_widgets' => 1,
        ]);

        return $this->view->render($response, 'contacts/addcontact.twig', array(
        ));
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

