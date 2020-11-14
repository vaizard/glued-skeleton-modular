<?php

declare(strict_types=1);

namespace Glued\Contacts\Controllers;

use Carbon\Carbon;
use Defr\Ares;
use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;
use Glued\Contacts\Classes\CZ as CZ;
use Glued\Contacts\Classes\EU;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Sabre\VObject;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
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
    //   trade (trading name)
    //   intl[] / translated name { "en": "vaizard institute"}
    // nickname[]
    // -------------------
    // gender
    // dob / birthday
    // dod / deathday
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

 

    private function concat(array $arrayOfStrings): string {
      return implode(' ', array_filter(array_map('trim',$arrayOfStrings)));
    }

    public function create(Request $request, Response $response, array $args = []): Response {
        $req = (array)$request->getParsedBody();
        //$req = json_decode(json_encode((object)$req));
        $builder = new JsonResponseBuilder('contacts', 1);

        $defs['user'] = $GLOBALS['_GLUED']['authn']['user_id'];
        $defs['id'] = 0;
        $defs['_v'] = (int) 1;
        $defs['_s'] = 'contacts';
        $l = $defs;
        $n = $defs;
        $do = [ 'n' => false, 'l' => false ];

        $rel = $req['contacts_items_create_n_role'] ?? '';

        if (($req['contacts_items_create_n_given'] ?? false) or ($req['contacts_items_create_n_family'] ?? false) or ($req['contacts_items_create_n_email'] ?? false) or ($req['contacts_items_create_n_phone'] ?? false)) {
            $n['n']['prefix'] = $req['contacts_items_create_n_prefix'];
            $n['n']['given'] = $req['contacts_items_create_n_given'];
            $n['n']['family'] = $req['contacts_items_create_n_family'];
            $n['n']['suffix'] = $req['contacts_items_create_n_suffix'];
            $n['fn'] = $this->concat([ $n['n']['prefix'],$n['n']['given'],$n['n']['family'],$n['n']['suffix'] ]);
            $n['email'] = $req['contacts_items_create_n_email'];
            $n['phone'] = $req['contacts_items_create_n_phone'];
            $n['addr']['unstructured'] = $req['contacts_items_create_n_addr'];
            $n['dob'] = $req['contacts_items_create_n_dob'];
            $n['note'] = $req['contacts_items_create_n_note'];
            $do['n'] = true;
        }
    
        if ($req['contacts_items_create_l_name'] ?? false) {
          $l['fn'] = $req['contacts_items_create_l_name'];
          $l['addr']['unstructured'] = $req['contacts_items_create_l_addr'];
          $l['nat'][0]['coutnry'] = $req['contacts_items_create_l_nat'];
          $l['nat'][0]['regid'] = $req['contacts_items_create_l_regid'];
          $l['nat'][0]['vatid'] = $req['contacts_items_create_l_vatid'];
          $l['nat'][0]['regby'] = $req['contacts_items_create_l_regby'];
          $l['note'] = $req['contacts_items_create_l_note'];
          $do['l'] = true;
        }

        // TODO replace true with validation check against schema ($result->isValid())
        if (true) {
            if ($do['l']) {
              $row = array (
                  'c_domain_id' => 7,//(int)$req['domain'], 
                  'c_user_id' => (int)$GLOBALS['_GLUED']['authn']['user_id'],
                  'c_json' => json_encode($l),
                  'c_attr' => '{}'
              );
              try { $l_req['id'] = $this->utils->sql_insert_with_json('t_contacts_objects', $row); } catch (Exception $e) { 
                  throw new HttpInternalServerErrorException($request, $e->getMessage());  
              }
            }
            if ($do['n']) {
              $row = array (
                  'c_domain_id' => 7,//(int)$req->domain, 
                  'c_user_id' => (int)$GLOBALS['_GLUED']['authn'],
                  'c_json' => json_encode($n),
                  'c_attr' => '{}'
              );
              try { $n_req['id'] = $this->utils->sql_insert_with_json('t_contacts_objects', $row); } catch (Exception $e) { 
                  throw new HttpInternalServerErrorException($request, $e->getMessage());  
              }
            }
            if ($do['n'] and $do['l']) {
              $type = 0;
              $row = [
                  "contact_id_1" => $l_req['id'],
                  "contact_id_2" => $n_req['id'],
                  "type" => $type,
                  "label" => $rel
              ];
              try { $this->db->insert('t_contacts_rels',$row); } catch (Exception $e) { 
                  throw new HttpInternalServerErrorException($request, $e->getMessage());  
              }

              $row = [
                  "contact_id_2" => $l_req['id'],
                  "contact_id_1" => $n_req['id'],
                  "type" => -$type,
                  "label" => $rel
              ];
              try { $this->db->insert('t_contacts_rels',$row); } catch (Exception $e) { 
                  throw new HttpInternalServerErrorException($request, $e->getMessage());  
              }
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



    public function list(Request $request, Response $response, array $args = []): Response {
      $builder = new JsonResponseBuilder('contacts', 1);
      //$json = "JSON_MERGE(t_fin_trx.c_json, JSON_OBJECT('account_name',t_fin_accounts.c_json->>'$.name'), JSON_OBJECT('account_color',t_fin_accounts.c_json->>'$.color'), JSON_OBJECT('account_icon',t_fin_accounts.c_json->>'$.icon'))";
      $json = "JSON_MERGE(t_contacts_objects.c_json, JSON_OBJECT('justfun','1'))";
      $result = $this->db->get('t_contacts_objects', null, [ $json ]);
      $key = array_keys($result[0])[0];
      $data = [];
      foreach ($result as $obj) {
        $data[] = json_decode($obj[$key]);
      }
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);

      $payload = $builder->withData((array)$data)->withCode(200)->build();
      return $response->withJson($payload);
    }



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function cz_names(Request $request, Response $response, array $args = []): Response {
        $cz = new CZ($this->c);
        $builder = new JsonResponseBuilder('contacts.search', 1);
        $search_string = $args['name'];
        $result = [];
        
      if (strlen($search_string) < 3) {
         $payload = $builder->withMessage('Please use at least 3 characters for your search.')->withCode(200)->build();
         return $response->withJson($payload);
      }

      // Query $services (or get a cached response if available) and store
      // it in $new. Normalize $new by using `regid` as a key ($indexed).
      // Merge data with array_replace_recursive().
      $services = [ 'names_rzp', 'names_ares', 'names_justice' ];
      foreach ($services as $svc) {
          $cnf = $cz->urikey($svc, $search_string); $raw_result = null;
          if ($this->fscache->has($cnf['key'])) {
              $new = $this->fscache->get($cnf['key']);
              if (is_array($new)) foreach ($new as $item)  $indexed[ $item['nat'][0]['regid'] ] = $item; 
              $result = array_replace_recursive($result, $indexed ?? []);
          } else {
              $new = $cz->$svc($search_string, $raw_result);
              if (is_array($new)) foreach ($new as $item)  $indexed[ $item['nat'][0]['regid'] ] = $item; 
              $result = array_replace_recursive($result, $indexed ?? []);
              if (!is_null($new)) $this->fscache->set($cnf['key'], $indexed, 3600); // 60 minutes
          }
      }

      // Drop the regid as a key
      $final = [];
      foreach ($result as $item) $final[] = $item;
     
      $payload = $builder->withData((array)$final)->withCode(200)->build();
      return $response->withJson($payload);
    }
    
    // TODO: search by reg-id (ičo) fails because cz_ids gives now data in quite a different format to that of cz_names

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    public function cz_ids(Request $request, Response $response, array $args = []): Response {
      // TODO this function is pretty slow. look where we loose speed.
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
      if ($this->fscache->has($cnf['key'])) {
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
      $nested[0] = $result;
      $payload = $builder->withData((array)$nested)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function eu_ids(Request $request, Response $response, array $args = []): Response {
      // TODO this function is pretty slow. look where we loose speed.
      $builder = new JsonResponseBuilder('contacts.search', 1);
      $eu = new EU($this->c);
      $id = mb_strtoupper($args['id']);




      if ((strlen($id) < 6) or ($eu->validate_vat($id) === false)) {
         $payload = $builder->withMessage('Not a valid EU VAT-ID.')->withCode(200)->build();
         return $response->withJson($payload);
      }
      $result = [];
      
      $cnf = $eu->urikey('vies', $id); $raw_result = null;
      if ($this->fscache->has($cnf['key'])) {
          $new = $this->fscache->get($cnf['key']);
          $result = array_replace_recursive($result, $new ?? []);
      } else {
          $new = $eu->vies($id, $raw_result) ?? [];
          $result = array_replace_recursive($result, $new ?? []);
          if (!is_null($new)) $this->fscache->set($cnf['key'], $result, 3600); // 60 minutes
      }
      if (is_null($new)) { $query['vies']['status'] = 'Error'; } else { $query['vies']['status'] = 'OK'; }

      $nested[0] = $result;
      $payload = $builder->withData((array)$nested)->withCode(200)->build();
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
        
        /*
            'json_schema_output' => $jsf_schema,
            'json_uischema_output' => $jsf_uischema,
            'json_formdata_output' => $jsf_formdata,
            'json_onsubmit_output' => $jsf_onsubmit
        */
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

    

}

