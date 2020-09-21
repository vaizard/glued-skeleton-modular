<?php

declare(strict_types=1);

namespace Glued\Fin\Controllers;

use Carbon\Carbon;
use \Opis\JsonSchema\Loaders\File as JSL;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Symfony\Component\DomCrawler\Crawler;
require_once(__ROOT__ . '/vendor/globalcitizen/php-iban/php-iban.php');

class FinController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */

    private function cz_valid_bank_account($accNumber) {
        $matches = [];
        if (!preg_match('/^(?:([0-9]{1,6})-)?([0-9]{2,10})\/([0-9]{4})$/', $accNumber, $matches)) {
            return false;
        }
        $weights = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];
        $prefix = str_pad($matches[1], 10, '0', STR_PAD_LEFT);
        $main   = str_pad($matches[2], 10, '0', STR_PAD_LEFT);
        // Check prefix
        $checkSum = 0;
        for ($i=0; $i < strlen($prefix); $i++) {
            $checkSum += $weights[$i] * (int)$prefix[$i];
        }
        if ($checkSum % 11 !== 0) {
            return false;
        }
        // Check main part
        $checkSum = 0;
        for ($i=0; $i < strlen($main); $i++) {
            $checkSum += $weights[$i] * (int)$main[$i];
        }
        if ($checkSum % 11 !== 0) {
            return false;
        }
        return true;
    }

    private function cz_number_to_iban(string $accNumber, string $countryCode = 'CZ'): ?string {
        if(!$this->cz_valid_bank_account($accNumber)
            || !in_array($countryCode, ['CZ', 'SK'])
            || !preg_match('/^(?:([0-9]{2,6})-)?([0-9]{2,10})\/([0-9]{4})$/', $accNumber, $matches)
        ) {
            return null;
        }
        $bban = $matches[3].str_pad($matches[1], 6, '0', STR_PAD_LEFT).str_pad($matches[2], 10, '0', STR_PAD_LEFT);
        $iban = 'CZ' . '00' . $bban;
        $iban = iban_set_checksum($iban);
        if (verify_iban($iban,$machine_format_only=true)) {
            return $iban;
        } else {
            return false;
        }
    }




    public function accounts_sync(Request $request, Response $response, array $args = []): Response {

      $account_uid = (int)$args['uid'];
      $this->db->where('c_uid', $account_uid);
      $obj = $this->db->getOne('t_fin_accounts', ['c_json', 'c_ts_synced']);
      $doc = json_decode($obj['c_json'], true);
      $doc['synced'] = $obj['c_ts_synced'];
      $this->db->where('c_uid', $account_uid);
      $this->db->update('t_fin_accounts', ['c_ts_synced' => $this->db->now() ]);

      $date_from = (string)isset($args['from']) ?? '2020-01-01';
      $date_to   = (string)$args['to'] ?? date('Y-m-d');

      die();
      $uri = 'https://www.fio.cz/ib_api/rest/periods/'.$doc['config']['token'].'/2020-01-01/2020-08-08/transactions.json';
      $curl_handle = curl_init();
      $curl_options = array_replace( $this->settings['curl'], [ CURLOPT_URL => $uri ] );
      curl_setopt_array($curl_handle, $curl_options);

      $ext_types = [
        'Příjem převodem uvnitř banky' => [
          'electronic' => 1,
          'flow' => 'i',
          'withinbank' => 1,
          'dscr' => 'Inbound electronic transfer within the bank',
        ],
        'Platba převodem uvnitř banky' => [
          'electronic' => 1,
          'flow' => 'o',
          'withinbank' => 1,
          'dscr' => 'Outbound electronic transfer within the bank',
        ],
        'Vklad pokladnou' => [
          'bank-cashier' => 1,
          'flow' => 'i',
          'dscr' => 'Deposit at the cashier',
        ],
        'Výběr pokladnou' => [
          'bank-cashier' => 1,
          'flow' => 'o',
          'dscr' => 'Deposit at the cashier',
        ],
        'Bezhotovostní příjem' => [
          'electronic' => 1,
          'flow' => 'i',
          'dscr' => 'Inbound electronic transfer',
        ],
        'Bezhotovostní platba' => [
          'electronic' => 1,
          'flow' => 'o',
          'dscr' => 'Outbound electronic transfer',
        ],
        'Platba kartou' => [
          'electronic' => 1,
          'card' => 1,
          'flow' => 'o',
          'dscr' => 'Card payment',
        ],
        'Poplatek' => [
          'electronic' => 1,
          'fee' => 1,
          'flow' => 'o',
          'dscr' => 'Fee',
        ],
        'Platba v jiné měně' => [
          'electronic' => 1,
          'flow' => 'o',
          'fx' => 1,
          'dscr' => 'Outbound electronic transfer in foreign currency',
        ],
        'Poplatek - platební karta' => [
          'electronic' => 1,
          'fee' => 1,
          'card' => 1,
          'flow' => 'o',
          'dscr' => 'Fee (card services)',
        ],
        'Inkaso' => [
          'electronic' => 1,
          'cash-collect' => 1,
          'flow' => 'o',
          'dscr' => 'Cash collection',
        ],
        'Okamžitá příchozí platba' => [
          'electronic' => 1,
          'flow' => 'i',
          'instant' => 1,
          'dscr' => 'Inbound instant electronic transfer',
        ],
        'Okamžitá odchozí platba' => [
          'electronic' => 1,
          'flow' => 'o',
          'instant' => 1,
          'dscr' => 'Outbound instant electronic transfer',
        ],
      ];

      $data = (array)json_decode(curl_exec($curl_handle), true);
      foreach ($data['accountStatement']['transactionList']['transaction'] as $trx) {
          $trx = (array)$trx;
          $helper = [];
          //print_r($trx);
          //die();
          $helper['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
          $helper['order']['uuid'] = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
          $helper['order']['created_by_name'] = $trx['column9']['value'] ?? '';
          $helper['order']['created_by_uid'] = "";
          $helper['order']['created_dt'] = "";
          $helper['order']['authed_by_name'] = $trx['column9']['value'] ?? '';
          $helper['order']['authed_by_uid'] = "";
          $helper['order']['authed_dt'] = "";
          $helper['dt'] = (new \DateTime($trx['column0']['value']))->format(DATE_W3C);
          $helper['volume'] = $trx['column1']['value'];
          $helper['currency'] = $trx['column14']['value'];
          if (verify_iban($trx['column2']['value'] ?? '',$machine_format_only=true)) {
              $helper['offset']['account_iban'] = $trx['column2']['value'];
              $helper['offset']['bank_bic'] = $trx['column26']['value'] ?? '';
              $helper['intl']['volume'] = (float)explode(' ', (string)$trx['column18']['value'] ?? '')[0];
              $helper['intl']['currency'] = explode(' ', (string)$trx['column18']['value'] ?? '')[1];
              if (((float)$helper['intl']['volume'] ?? 0) != 0) {
                 $helper['intl']['rate'] = abs($helper['volume'] / ((float)$helper['intl']['volume']));
              }
          } else {
              $helper['offset']['name'] = '';
              $helper['offset']['address'] = '';
              $helper['offset']['id'] = '';
              $helper['offset']['uid'] = '';
              $helper['offset']['bank_name'] = $trx['column12']['value'] ?? '';
              $helper['offset']['bank_address'] = '';
              $helper['offset']['bank_code'] = $trx['column3']['value'] ?? '';
              $helper['offset']['bank_bic'] = $trx['column26']['value'] ?? '';
              $helper['offset']['account_number'] = $trx['column2']['value'] ?? '';
              $helper['offset']['account_name'] = $trx['column10']['value'];
              $helper['offset']['account_iban'] = $this->cz_number_to_iban($helper['offset']['account_number'].'/'.$helper['offset']['bank_code']);
          }
          $helper['ref']['variable'] = $trx['column5']['value'] ?? '';
          $helper['ref']['specific'] = $trx['column6']['value'] ?? '';
          $helper['ref']['internal'] = $trx['column7']['value'] ?? ''; // uziv. identifikace
          $helper['ref']['constant'] = $trx['column4']['value'] ?? '';
          $helper['message'] = $trx['column16']['value'] ?? ''; // to recipient
          $helper['comment'] = $trx['column25']['value'] ?? ''; // lokal koment
          $helper['specification'] = $trx['column18']['value'] ?? '';  // upřesnění (cizoměn)
          $helper['ext']['trx_id'] = $trx['column22']['value'];
          $helper['ext']['order_id'] = $trx['column17']['value'] ?? ''; 
          $helper['ext']['order_by'] = $trx['column9']['value'] ?? ''; 
          $helper['ext']['type'] = $trx['column8']['value'] ?? ''; // type
          if (array_key_exists($helper['ext']['type'], $ext_types)) {
              $helper['type'] = $ext_types[$helper['ext']['type']];  
          } else {
              $flow = 'i';
              if ($helper['volume'] > 0) {
                  $flow = 'i';
              } 
              if ($helper['volume'] < 0) {
                  $flow = 'o';
              }
              $helper['type'] = [
                  'dscr' => 'Other / unknown',
                  'flow' => $flow,
              ];
          }
              
          $final[] = $helper;
          $data = Array ("c_json" => json_encode($helper),
                         "c_account_id" => $account_uid    // TODO add foreign index
                         //"createdAt" => $db->now(),
                         //"updatedAt" => $db->now(),
          );
          $updateColumns = Array ("c_ts_modified");
          $lastInsertId = "c_uid";
          //$this->db->onDuplicate($updateColumns, $lastInsertId);
          $id = $this->db->insert ('t_fin_trx', $data);
          //echo "<br>".$this->db->getLastError();
          

      }
      
      curl_close($curl_handle);
      //die();
      $builder = new JsonResponseBuilder('fin.sync.banks', 1);
      $data = $final;
      $payload = $builder->withData((array)$data)->withCode(200)->build();
      return $response->withJson($payload);

    }
    public function trx_list(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.trx.list', 1);
        $search_string = $args['name'];
        $result = 'aha';
      $payload = $builder->withData((array)$result)->withCode(200)->build();
      print_r($payload);
      //die();
      //return $response->withJson($payload);
      //print("<pre>".print_r($result,true)."</pre>");
      //return $response;
    }


    // ==========================================================
    // ACCOUNTS UI
    // ==========================================================

    public function accounts_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Fin/Views/accounts.twig', [
            'domains' => $domains,
            'currencies' => $this->iso4217->getAll()
        ]);
    }

    // ==========================================================
    // ACCOUNTS API
    // ==========================================================

    private function sql_accounts_list() {
        $data = $this->db->rawQuery("
            SELECT
                c_domain_id as 'domain',
                t_fin_accounts.c_user_id as 'user',
                t_core_users.c_name as 'user_name',
                t_core_domains.c_name as 'domain_name',
                t_fin_accounts.c_uid as 'id',
                t_fin_accounts.c_json->>'$._s' as '_s',
                t_fin_accounts.c_json->>'$._v' as '_v',
                t_fin_accounts.c_json->>'$.type' as 'type',
                t_fin_accounts.c_json->>'$.currency' as 'currency',
                t_fin_accounts.c_json->>'$.name' as 'name',
                t_fin_accounts.c_json->>'$.description' as 'description',
                t_fin_accounts.c_json->>'$.config' as 'config',
                t_fin_accounts.c_ts_synced as 'ts_synced'
            FROM `t_fin_accounts` 
            LEFT JOIN t_core_users ON t_fin_accounts.c_user_id = t_core_users.c_uid
            LEFT JOIN t_core_domains ON t_fin_accounts.c_domain_id = t_core_domains.c_uid
        ");
        return $data;
    }


    public function accounts_list(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('fin.accounts', 1);
        $payload = $builder->withData((array)$this->sql_accounts_list())->withCode(200)->build();
        return $response->withJson($payload);
        // TODO handle errors
        // TODO the withData() somehow escapes quotes in t_fin_accounts.c_json->>'$.config' 
        //      need to figure out where this happens and zap it.
    }


    public function accounts_patch(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.accounts', 1);

        // Get patch data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];

        // Get old data
        $this->db->where('c_uid', $req['id']);
        $doc = $this->db->getOne('t_fin_accounts', ['c_json'])['c_json'];
        if (!$doc) { throw new HttpBadRequestException( $request, __('Bad source ID.')); }
        $doc = json_decode($doc);

        // TODO replace this lame acl with something propper.
        if($doc->user != $req['user']) { throw new HttpForbiddenException( $request, 'You can only edit your own calendar sources.'); }

        // Patch old data
        $doc->description = $req['description'];
        $doc->name = $req['name'];
        $doc->type = $req['type'];
        $doc->domain = (int)$req['domain'];
        //$doc->config = json_decode($req['config']);

        if (array_key_exists('config', $req)) {
          $config = json_decode(trim($req['config']), true);
          if (json_last_error() !== 0) throw new HttpBadRequestException( $request, __('Config contains invalid json.'));
          $doc->config = $config;
        } else { $doc->config = new \stdClass(); }
        if (!array_key_exists('currency', $req)) { $doc->currency = ''; }

        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain

        // load the json schema and validate data against it

        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($doc, $schema);
print_r($result); die();
        if ($result->isValid()) {
            $row = [ 'c_json' => json_encode($doc) ];
            $this->db->where('c_uid', $req['id']);
            $id = $this->db->update('t_fin_accounts', $row);
            if (!$id) { throw new HttpInternalServerErrorException( $request, __('Updating of the account failed.')); }
        } else { throw new HttpBadRequestException( $request, __('Invalid account data.')); }


        // Success
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);  
    }


    public function accounts_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.accounts', 1);
        $req = $request->getParsedBody();
        
        if (array_key_exists('config', $req)) {
          $config = json_decode(trim($req['config']), true);
          if (json_last_error() !== 0) throw new HttpBadRequestException( $request, __('Config contains invalid json.'));
          $req['config'] = $config;
        } else { $req['config'] = new \stdClass(); }
        if (!array_key_exists('currency', $req)) { $req['currency'] = ''; }

        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = 0;
        $req['_v'] = (int) 1;
        $req['_s'] = 'fin.accounts';

        // TODO check again if user is member of a domain that was submitted
        if ( isset($req['domain']) ) { $req['domain'] = (int) $req['domain']; }

        // convert body to object
        $req = json_decode(json_encode((object)$req));
  
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_domain_id' => (int)$req->domain, 
                'c_user_id' => (int)$req->user,
                'c_json' => json_encode($req),
                'c_attr' => '{}'
            );
            try { $req->id = $this->utils->sql_insert_with_json('t_fin_accounts', $row); } catch (Exception $e) { 
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


    public function accounts_delete(Request $request, Response $response, array $args = []): Response {
        try { 
          $this->db->where('c_uid', (int)$args['uid']);
          $this->db->delete('t_fin_accounts');
        } catch (Exception $e) { 
          throw new HttpInternalServerErrorException($request, $e->getMessage());  
        }
        $builder = new JsonResponseBuilder('fin.accounts', 1);
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }




}

