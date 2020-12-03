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

    public function contacts_post_api(Request $request, Response $response, array $args = []): Response {
        // Init stuff
        $builder = new JsonResponseBuilder('contacts', 1);
        $req = (array)$request->getParsedBody();
        $do = [ 'fn' => false, 'fl' => false ];
        $defs['user'] = $GLOBALS['_GLUED']['authn']['user_id'];
        $defs['id'] = 0;
        $defs['_v'] = (int) 1;
        $defs['_s'] = 'contacts';
        $type = 0;
        $row = array (
            'c_domain_id' => 1, // TODO change to domain (int)$req['domain'], 
            'c_user_id' => (int)$GLOBALS['_GLUED']['authn']['user_id'],
            'c_attr' => '{}'
        );

        // Get form data (natural person)
        if (($req['contacts_items_create_n_given'] ?? false) or ($req['contacts_items_create_n_family'] ?? false) or ($req['contacts_items_create_n_email'] ?? false) or ($req['contacts_items_create_n_phone'] ?? false)) {
            $fn['n']['prefix'] = $req['contacts_items_create_n_prefix'];
            $fn['n']['given'] = $req['contacts_items_create_n_given'];
            $fn['n']['family'] = $req['contacts_items_create_n_family'];
            $fn['n']['suffix'] = $req['contacts_items_create_n_suffix'];
            $fn['fn'] = $this->concat([ $fn['n']['prefix'],$fn['n']['given'],$fn['n']['family'],$fn['n']['suffix'] ]);
            $fn['email'][0]['value'] = $req['contacts_items_create_n_email'];
            $fn['phone'][0]['value'] = $req['contacts_items_create_n_phone'];
            $fn['addr']['unstructured'] = $req['contacts_items_create_n_addr'];
            $fn['dob'] = $req['contacts_items_create_n_dob'];
            $fn['note'] = $req['contacts_items_create_n_note'];
            $fn['role'][0]['name'] = $req['contacts_items_create_n_role'] ?? '';
            $fn['role'][0]['dt_from'] = '';
            $do['fn'] = true;
        }

        // Get form data (legal person / company)
        if ($req['contacts_items_create_l_name'] ?? false) {
            $fl['fn'] = $req['contacts_items_create_l_name'];
            $fl['addr']['unstructured'] = $req['contacts_items_create_l_addr'];
            $fl['nat'][0]['country'] = $req['contacts_items_create_l_nat'];
            $fl['nat'][0]['regid'] = $req['contacts_items_create_l_regid'];
            $fl['nat'][0]['vatid'] = $req['contacts_items_create_l_vatid'];
            $fl['nat'][0]['regby'] = $req['contacts_items_create_l_regby'];
            $fl['uri'][0]['value'] = $req['contacts_items_create_l_web'];
            $fl['uri'][0]['label'] = ($req['contacts_items_create_l_web']) ? 'website' : null;
            $fl['note'] = $req['contacts_items_create_l_note'];
            $do['fl'] = true;
        }
        // Get additional data about the legal person
        if ($do['fl']) {

            // Czech registers
            if ($fl['nat'][0]['country'] == 'CZ') {
                // If submitted company data doesn't have a regid, guess it
                if ($fl['nat'][0]['regid']=='') $fl['nat'][0]['regid'] = substr($fl['nat'][0]['vatid'], 2);
                // Get data from registers according to regid ()
                $cz = new CZ($this->c);
                $full = $cz->ids($fl['nat'][0]['regid']);
                if ($full['fn'] == $fl['fn']) {
                    // If {submitted company name} == {company name in registers}
                    // Override submitted form data with data from registers
                    $l = $full;
                    $n = $full['people'];
                    if ($do['fn']) $n[] = $fn;
                    if (is_array($n)) $do['fn'] = true;
                    unset($l['people']);
                } else {
                    $l = $fl;
                    if ($do['fn']) $n[0] = $fn;
                }
            }

        // No company data, only natural person
        } else {
            if ($do['fn']) $n[0] = $fn;          
        }


      try { 
          if (true) { // TODO replace true with validation check against schema ($result->isValid())
              if ($do['fl']) {
                $row['c_json'] = json_encode($l ?? $fl);
                $l_req['id'] = $this->utils->sql_insert_with_json('t_contacts_objects', $row); 
              }

              if ($do['fn']) {
                foreach ($n as $person) {
                  $row['c_json'] = json_encode($person);
                  $n_req['id'] = $this->utils->sql_insert_with_json('t_contacts_objects', $row); 
                  if ($do['fn'] and $do['fl']) {
                      foreach ($person['role'] as $rel) {
                          $rrow1 = [
                              "c_uid1" => $l_req['id'],
                              "c_uid2" => $n_req['id'],
                              "c_type" => $type,
                              "c_label" => $rel['name'] ?? null,
                              "c_dt_from" => $rel['dt_from'] ?? null,
                              "c_dt_till" => $rel['dt_till'] ?? null
                          ];
                          $rrow2 = [
                              "c_uid1" => $n_req['id'],
                              "c_uid2" => $l_req['id'],
                              "c_type" => -$type,
                              "c_label" => $rel['name'] ?? null,
                              "c_dt_from" => $rel['dt_from'] ?? null,
                              "c_dt_till" => $rel['dt_till'] ?? null
                          ];
                          $this->db->insert('t_contacts_rels',$rrow1);
                          $this->db->insert('t_contacts_rels',$rrow2); 
                      }
                  }
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
      } catch (Exception $e) { 
          throw new HttpInternalServerErrorException($request, $e->getMessage());  
      }        
    }



    public function list(Request $request, Response $response, array $args = []): Response {
      $builder = new JsonResponseBuilder('contacts', 1);
      $json = "t_contacts_objects.c_json";
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

//SELECT JSON_ARRAYAGG(JSON_OBJECT('uid', c_uid2, 'label', c_label, 'dt_from', c_dt_from)) from t_contacts_rels where c_uid1 = 29;
//SELECT c_uid2, JSON_arrayAGG(JSON_OBJECT('uid', c_uid2, 'label', c_label, 'dt_from', c_dt_from)) from t_contacts_rels where c_uid1 = 29 GROUP BY c_uid2;
//SELECT JSON_OBJECT( c_uid2, JSON_ARRAYAGG(JSON_OBJECT('uid', c_uid2, 'label', c_label, 'dt_from', c_dt_from))) from t_contacts_rels where c_uid1 = 29 GROUP BY c_uid2;

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
              if (is_array($new)) foreach ($new as $item) $indexed[ $item['nat'][0]['regid'] ] = $item; 
              $result = array_replace_recursive($result, $indexed ?? []);
          } else {
              $new = $cz->$svc($search_string, $raw_result);
              if (is_array($new)) foreach ($new as $item)  $indexed[ $item['nat'][0]['regid'] ] = $item; 
              $result = array_replace_recursive($result, $indexed ?? []);
              if (!is_null($new) and ($new != false)) $this->fscache->set($cnf['key'], $indexed, 3600); // 60 minutes
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

      $builder = new JsonResponseBuilder('contacts.search', 1);
      $cz = new CZ($this->c);
      $id = $args['id'];

      if (strlen($id) != 8) {
         $payload = $builder->withMessage('Czech company IDs are 8 numbers in total.')->withCode(200)->build();
         return $response->withJson($payload);
      }

      $result[0] = $cz->ids($id);
      $payload = $builder->withData((array)$result)->withCode(200)->build();
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
      if ($this->fscache->has($cnf['key'].'d')) {
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
        $uid = $args['uid'] ?? null;
        $domains = $this->db->get('t_core_domains');

        if ($uid) {
            $this->db->where('c_uid', $uid);
            $data = $this->db->get('t_contacts_objects');
            return $this->render($response, 'Contacts/Views/object.twig', [
                'domains' => $domains,
                'data' => $data//$this->sellers_get_sql($args),
            ]);          
        }
        return $this->render($response, 'Contacts/Views/collection.twig', [
            'domains' => $domains,
        ]);


    }


/*
    public function collection_ui(Request $request, Response $response, array $args = []): Response
    {
      $uribase = strtolower(parse_url((string)$request->getUri(), PHP_URL_SCHEME)).'://'.strtolower(parse_url((string)$request->getUri(), PHP_URL_HOST));

      $jsf_schema   = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.schema');
      $jsf_uischema = file_get_contents(__ROOT__.'/glued/Contacts/Controllers/Schemas/contacts.v1.formui');
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
           /*
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
*/

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

