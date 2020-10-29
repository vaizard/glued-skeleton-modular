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
use Phpfastcache\Helper\Psr16Adapter;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Glued\Contacts\Classes\CZ as CZ;
use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;

// grabbing
use Symfony\Component\DomCrawler\Crawler;

class ContactsController extends AbstractTwigController
{

    // src = zdroj
    // link = prilinkovany dokument
    // ext = data ve spesl formatu
    // -----------------
    // kind
    //   legal = 0/1
    //   natural = 0/1
    // -----------------
    // fn
    // n
    //   prefix
    //   given
    //   family
    //   maiden
    //   suffix
    //   prior[] (prior family names)
    // nickname[]
    // -------------------
    // gender
    // bday / birthday
    // dday / deathday
    // photo
    // marital_status
    // -------------------
    // addr[]
    //   kind=(main,billing,permanent,temporary,shipping)
    //   label
    //   unstructured
    //   full (correctly structured)
    //   street
    //   locacity
    //   zip
    //   country
    //   district
    //   locacity
    //   quarter
    //   streetnumber
    //   conscriptionnumber
    //   doornumber
    //   floor
    //   pobox
    //   ref['cz.ruian']
    //  ------------------
    // acc[]
    //   bank_code
    //   bank_bic
    //   bank_name
    //   bank_addr
    //   account_number
    //   account_iban
    //   account_currency
    //   account_holder
    //   ref['source-name']
    //  --------------------
    // domicile // for taxation puroposses
    // nat[] // nationality
    //   country
    //   regby
    //   regdt
    //   regid
    //   vatid
    //   natid
    //   docid
    //     kind={personal-id|passport|...}
    //     docid
    //     issby
    //     issdt
    //     expdt
    // ---------------
    // email
    //   label
    //   kind
    //   valid
    //   value
    // tel
    // uri
    // social
    // impp / Defines an instant messenger handle. This was added to the official vCard specification in version 4.0.
    // ---------------
    // language
    // anniversary { labeel, date }
    // expertise / A professional subject area that the person has knowledge of. (field|level[value=expert,assessed_by=who-says-contact-has-expert-level])
    // hobby / A recreational activity that the person actively engages in.
    // interest / A recreational activity that the person is interested in, but does not necessarily take part in.
    // education
    // licenses / Certifications and licences
    // occupation
    // ---------------
    // rel: // relationships
    //   kind: assistant, brother, child, domestic-partner, father, friend, manager, mother, business-partner, relative, sister, spouse, acquantance (personal)
    //         employee, employer, board member, officer, volunteer (professional)
    //         referred-by (other)
    //   label: i.e. CEO
    //         
    //               
    // street address




    // source
    // tz





    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
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
      $builder = new JsonResponseBuilder('contacts.search', 1);
      $payload = $builder->withData((array)$data)->withCode(200)->build();
      return $response->withJson($payload);

    }

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function cz_names(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('contacts.search', 1);
        $search_string = $args['name'];
        
      if (strlen($search_string) < 3) {
         $payload = $builder->withMessage('Please use at least 3 characters for your search.')->withCode(200)->build();
         return $response->withJson($payload);
      }
      $result_justice = [];
      $uri = 'https://or.justice.cz/ias/ui/rejstrik-$firma?jenPlatne=PLATNE&nazev='.$search_string.'&polozek=500';
      $crawler = $this->goutte->request('GET', $uri);
      $crawler->filter('div.search-results > ol > li.result')->each(function (Crawler $table) use (&$result_justice) {
          $r['org'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(2) > strong')->text();
          $r['regid'] = $table->filter('div > table > tbody > tr:nth-child(1) > td:nth-child(4) > strong')->text();
          $r['addr'] = $table->filter('div > table > tbody > tr:nth-child(3) > td:nth-child(2)')->text();
          $r['regby'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(2)')->text();
          $r['regdt'] = $table->filter('div > table > tbody > tr:nth-child(2) > td:nth-child(4)')->text();
          $result_justice[$r['regid']] = $r;
          //vatid
          //https://adisreg.mfcr.cz/adistc/DphReg?id=1&pocet=1&fu=&OK=+Search+&ZPRAC=RDPHI1&dic=29228107
      });

      $result = [];
      $uri = 'http://www.rzp.cz/cgi-bin/aps_cacheWEB.sh?VSS_SERV=ZVWSBJFND&Action=Search&PRESVYBER=0&PODLE=subjekt&ICO=&OBCHJM='.$search_string.'&VYPIS=1';
      $crawler = $this->goutte->request('GET', $uri);
      $crawler->filter('div#obsah > div.blok.data.subjekt')->each(function (Crawler $table) use (&$result) {
          $r['org'] = $table->filter('h3')->text();
          $r['org'] = preg_replace('/^[0-9]{1,2}. /', "", $r['org']);
          $r['addr'] = $table->filter('dd:nth-child(4)')->text();
          $r['regid'] = $table->filter('dd:nth-child(8)')->text();
          $result[$r['regid']] = $r;
      });

      $result = array_replace($result, $result_justice);
      
      $payload = $builder->withData((array)$result)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function cz_names_rzp(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('contacts.search', 1);
        $search_string = $args['name'];
        
      if (strlen($search_string) < 3) {
         $payload = $builder->withMessage('Please use at least 3 characters for your search.')->withCode(200)->build();
         return $response->withJson($payload);
      }
      $result = [];
      $uri = 'http://www.rzp.cz/cgi-bin/aps_cacheWEB.sh?VSS_SERV=ZVWSBJFND&Action=Search&PRESVYBER=0&PODLE=subjekt&ICO=&OBCHJM='.$search_string.'&VYPIS=1';
      $crawler = $this->goutte->request('GET', $uri);
      $crawler->filter('div#obsah > div.blok.data.subjekt')->each(function (Crawler $table) use (&$result) {
          $r['org'] = $table->filter('h3')->text();
          $r['org'] = preg_replace('/^[0-9]{1,2}. /', "", $r['org']);
          $r['addr'] = $table->filter('dd:nth-child(4)')->text();
          $r['regid'] = $table->filter('dd:nth-child(8)')->text();
          $result[] = $r;
      });
      $payload = $builder->withData((array)$result)->withCode(200)->build();
      return $response->withJson($payload);
    }


    

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    public function cz_ids(Request $request, Response $response, array $args = []): Response {
      $builder = new JsonResponseBuilder('contacts.search', 1);
      $cz = new CZ($this->c);
      $id = $args['id'];
      if (strlen($id) != 8) {
         $payload = $builder->withMessage('Czech company IDs are 8 numbers in total.')->withCode(200)->build();
         return $response->withJson($payload);
      }
      $result = [];

      // JUSTICE
      $cnf = $cz->urikey('ids_justice', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'].'e')) {
          $new = $this->fscache->get($cnf['key']);
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $cz->ids_justice($id, $raw_result);
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $result, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['ids_justice']['status'] = 'Error'; } else { $query['ids_justice']['status'] = 'OK'; }


      // ADISRWS
      $cnf = $cz->urikey('ids_adisrws', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'])) {
          $new = $cz->ids_adisrws($id, $raw_result);
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $cz->ids_adisrws($id, $raw_result);
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $new, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['ids_adisrws'] = [ 'status' => 'Error', 'message' => $raw_result ]; } else { $query['ids_adisrws']['status'] = 'OK'; }


      // VREO
      $cnf = $cz->urikey('ids_vreo', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'])) {
          $raw_result = $this->fscache->get($cnf['key']);
          $new = $cz->ids_vreo($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $cz->ids_vreo($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $raw_result, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['ids_vreo']['status'] = 'Error'; } else { $query['ids_vreo']['status'] = 'OK'; }


      // RZP
      $cnf = $cz->urikey('ids_rzp', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'])) {
          $raw_result = $this->fscache->get($cnf['key']);
          $new = $cz->ids_rzp($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $cz->ids_rzp($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $raw_result, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['ids_rzp']['status'] = 'Error'; } else { $query['ids_rzp']['status'] = 'OK'; }

      // VIES
      $cnf = $cz->urikey('vies', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'])) {
          $raw_result = $this->fscache->get($cnf['key']);
          $new = $cz->vies($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $cz->vies($id, $raw_result) ?? [];
          //if (!is_null($new) and ($result['fn'] != $new['fn'] ?? null)) unset($new['nat'][0]['vatid']);
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $raw_result, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['vies']['status'] = 'Error'; } else { $query['vies']['status'] = 'OK'; }

      // RESULT
      $result['query'] = $query;
      $payload = $builder->withData((array)$result)->withCode(200)->build();
      return $response->withJson($payload);
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

