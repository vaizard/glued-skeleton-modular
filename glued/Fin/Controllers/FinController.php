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
use Glued\Fin\Classes\Utils as FinUtils;
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





    public function accounts_sync(Request $request, Response $response, array $args = []): Response {

      // Args & init
      $builder = new JsonResponseBuilder('fin.sync.banks', 1);
      if (!array_key_exists('uid', $args)) {
        $payload = $builder->withMessage(__('Syncing multiple accounts not supported (yet).'))->withCode(500)->build();
        return $response->withJson($payload); 
      }
      $account_uid = (int)$args['uid'];
      

      // Fetch (locally stored) account properties
      $this->db->where('c_uid', $account_uid);
      $account_obj = $this->db->getOne('t_fin_accounts', ['c_json', 'c_ts_synced']);
      if (!is_array($account_obj)) {
        $payload = $builder->withMessage(__('Account not found.'))->withCode(404)->build();
        return $response->withJson($payload);
      }
      $account = json_decode($account_obj['c_json'], true);
      $account['synced'] = $account_obj['c_ts_synced'];


      // Fetch all (locally stored) transactions
      $this->db->where('c_account_id', $account_uid);
      $this->db->orderBy('c_trx_dt', 'Desc');
      $this->db->orderBy('c_ext_trx_id', 'Desc');
      $local_trxs = $this->db->get('t_fin_trx', null, ['c_trx_dt','c_ext_trx_id']);


      // Throttle in case of too many requests
      $t1 = Carbon::createFromFormat('Y-m-d H:i:s', $account['synced'], 'UTC'); // TODO: Replace hardcoded mysql timezone (UTC). See https://stackoverflow.com/questions/2934258/how-do-i-get-the-current-time-zone-of-mysql
      $t2 = Carbon::now();
      $tdiff = $t2->diffinSeconds($t1);
      if ($tdiff < 30) { 
        // TODO replace hard throttling with queuing
        $payload = $builder->withMessage(__('Account synchronization throttled. Last sync '.$tdiff.' seconds ago, please retry in ').(30 - $tdiff).' '.__('seconds'))->withCode(429)->build();
        return $response->withJson($payload);
      }


      // Set the fetch intervals & update sync time in account properties
      $date_from = (string)(new \DateTime($args['from'] ?? $local_trxs[0]['c_trx_dt'] ?? '2000-01-01'))->modify('-1 day')->format('Y-m-d');
      $date_to = (string)($args['to'] ?? date('Y-m-d'));
      $this->db->where('c_uid', $account_uid);
      $this->db->update('t_fin_accounts', [ 'c_ts_synced' => $this->db->now() ]);

      // FIO.CZ ACCOUNTS ================================================

      if ($account['type'] === 'fio_cz') {
        // Get transactions from the api, return tranactions not known locally
        $uri = 'https://www.fio.cz/ib_api/rest/periods/'.$account['config']['token'].'/'.$date_from.'/'.$date_to.'/transactions.json';
        $data = (array)json_decode($this->utils->fetch_uri($uri), true);
        if (!array_key_exists('accountStatement', $data)) { throw new HttpInternalServerErrorException( $request, __('Syncing with remote server failed.')); }
        $fin = new FinUtils();
        $data = $fin->fio_cz($data['accountStatement']['transactionList']['transaction'], [ 'account_id' => $account_uid ], $local_trxs);

        // Insert new transactions
        foreach ($data as $helper) {       
              $insertdata[] = [
                "c_json" => json_encode($helper),
                "c_account_id" => $account_uid
              ];
        }
        if (isset($insertdata)) {
          $ids = $this->db->insertMulti('t_fin_trx', $insertdata);
          $query = 'UPDATE `t_fin_trx` SET `c_json` = JSON_SET(`c_json`, "$.id", `c_uid`) WHERE (NOT `c_uid` = c_json->>"$.id") or (NOT JSON_CONTAINS_PATH(c_json, "one", "$.id"));';
          $this->db->rawQuery($query);
        }


        // Respond to client
        $msg = (isset($ids) ? count($ids).' items synced.' : 'Even with remote source, nothing to sync.');
        $payload = $builder->withMessage($msg)->withData((array)$data)->withCode(200)->build();
        return $response->withJson($payload);
      } 

      // LOCAL ACCOUNTS ================================================

      else {
        $payload = $builder->withMessage('Primary account data held locally, nothing to sync.')->withCode(200)->build();
        return $response->withJson($payload);
      }
    }


    public function trx_list(Request $request, Response $response, array $args = []): Response {
      $builder = new JsonResponseBuilder('fin.trx', 1);

      if (array_key_exists('uid', $args)) {
        $trx_uid = (int)$args['uid'];
        //$this->db->where('c_account_id', $account_uid);
        // TODO get access from rbac middleware here
      } else {
        // TODO get allowed IDs from rbac middleware here and construct $this->db->where() accordingly
      }
      
      // TODO seriously perf optimize the shit out of this
      //      - drop the JSON_MERGE and add the c_uid on insert
      //      - drop the json_decode and withJson, just add a json output to the JsonResponseBuilder and use relevant headers
      //      
      // SELECT JSON_ARRAYAGG(JSON_MERGE(t_fin_trx.c_json,JSON_OBJECT('id',t_fin_trx.c_uid),JSON_OBJECT('account_name',t_fin_accounts.c_json->>'$.name'),JSON_OBJECT('account_color',t_fin_accounts.c_json->>'$.color'),JSON_OBJECT('account_icon',t_fin_accounts.c_json->>'$.icon'))) FROM t_fin_trx LEFT JOIN t_fin_accounts ON t_fin_trx.c_account_id = t_fin_accounts.c_uid ORDER BY c_trx_dt DESC, c_ext_trx_id DESC 

      $this->db->orderBy('t_fin_trx.c_trx_dt', 'Desc');
      $this->db->orderBy('t_fin_trx.c_ext_trx_id', 'Desc');
      $this->db->join('t_fin_accounts', 't_fin_trx.c_account_id = t_fin_accounts.c_uid', 'LEFT');
      $json = "JSON_MERGE(t_fin_trx.c_json, JSON_OBJECT('account_name', t_fin_accounts.c_json->>'$.name', 'account_color', t_fin_accounts.c_json->>'$.color', 'account_type', t_fin_accounts.c_json->>'$.type', 'account_icon',t_fin_accounts.c_json->>'$.icon'))";
      $result = $this->db->get('t_fin_trx', null, [ $json ]);
      $key = array_keys($result[0])[0];
      $data = [];
      foreach ($result as $obj) {
        $data[] = json_decode($obj[$key]);
      }
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function trx_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        $accounts = $this->db->get('t_fin_accounts', null, ['c_uid as id', 'c_json->>"$.type" as type', 'c_json->>"$.name" as name', 'c_json->>"$.currency" as currency']);
        return $this->render($response, 'Fin/Views/trx.twig', [
            'domains' => $domains,
            'accounts' => $accounts,
            'currencies' => $this->iso4217->getAll()
        ]);
    }

 
    /**
     * A version of in_array() that does a sub string match on $needle
     *
     * @param  mixed   $needle    The searched value
     * @param  array   $haystack  The array to search in
     * @return boolean
     */
    public function get_opt_param($needle, array $haystack)
    {
        $filtered = array_filter($haystack, function ($item) use ($needle) {
            return false !== strpos($item, $needle);
        });
     
        //return !empty($filtered);
        return explode($needle, $filtered[1])[1];
    }

    public function trx_list_reduce_ui(Request $request, Response $response, array $args = []): Response {
        $params = explode('/', $args['params']);
        $where = null;
        $where_param = null;
        $where_query = '';
        print_r($params);

        if ($dt_from = $this->get_opt_param('dtfrom=', $params)) {
           $cond['query'] = "STR_TO_DATE(SUBSTRING(c_json->>'$.dt', 1, LENGTH(c_json->>'$.dt')-15),'%Y-%m-%d') >= ?";
           $cond['param'] = $dt_from; // TODO add regex match
           $where['dt_from'] = $cond;

        }

        if (in_array('reduce-iban', $params)) {
            if ($where) {
              $where_query = "WHERE " . $where['dt_from']['query'];
              $where_param[] = $where['dt_from']['param']; 
              //-- t_fin_trx.c_json->>'$.offset.account_iban'='CZ0920100000002400089939' and 
            }

            $query = "SELECT 
            JSON_ARRAYAGG( 
              JSON_OBJECT(
                'dt', STR_TO_DATE(SUBSTRING(c_json->>'$.dt', 1, LENGTH(c_json->>'$.dt')-15),'%Y-%m-%d'),
                'offset', JSON_OBJECT(
                  'account_number', c_json->>'$.offset.account_number',
                  'bank_code', c_json->>'$.offset.bank_code',
                  'account_iban', c_json->>'$.offset.account_iban',
                  'name', c_json->>'$.offset.name',
                  'id', c_json->>'$.offset.id'
                ),
                'ref', c_json->>'$.ref.freeform',
                'volume', c_json->>'$.volume',
                'comment', c_json->>'$.comment',
                'message', c_json->>'$.message',
                'account_id',c_json->>'$.account_id',
                'currency',c_json->>'$.currency'
              )
            ) as 'transactions'
            FROM `t_fin_trx`
            $where_query
            GROUP BY t_fin_trx.c_json->>'$.offset.account_iban'
            ";
        }

        $result = $this->db->rawQuery($query, $where_param) ?? [];
        $key = array_keys($result[0])[0];
        $data = [];
        foreach ($result as $obj) {
          $data[] = json_decode($obj[$key], true);
        }



        $domains = $this->db->get('t_core_domains');
        $accounts = $this->db->get('t_fin_accounts', null, ['c_uid as id', 'c_json->>"$.type" as type', 'c_json->>"$.name" as name', 'c_json->>"$.currency" as currency']);
        return $this->render($response, 'Fin/Views/trx-reduce.twig', [
            'domains' => $domains,
            'accounts' => $accounts,
            'data' => $data,
        ]);
    }


    // ==========================================================
    // COSTS UI
    // ==========================================================

    public function costs_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        
        // TODO - get this into a db to cross reference cost types to order/grant possibilities
        $cost_types = [
          '501' => 'spotřeba materiálu',
          '501.1' => 'přímý materiál', // na rekvizity, kostýmy, apod.
          '501.1.1' => 'spojovací materiál',
          '501.1.2' => 'brusivo, řezivo',
          '501.1.3' => 'stavebniny',
          '501.1.4' => 'barvy, laky, chemie',
          '501.1.5' => 'kovy',
          '501.1.6' => 'dřevo',
          '501.1.7' => 'plasty',
          '501.1.7' => 'drobné součástky',
          '501.1.8' => 'ochranné pomůcky',
          '501.1.9' => 'PHM',
          '501.1.10' => 'provozní kapaliny',
          '501.1.11' => 'drobná elektronika',
          '501.1.11' => 'náhradní díly',
          '501.2' => 'režijní materiál',
          '501.2.1' => 'kancelářské potřeby',
          '501.2.1' => 'čistící a hyg. prostředky',
          '501.2.1' => 'léky',
          '501.3' => 'knihy, časopisy, publikace',
          '502' => 'energie',
          '502.1' => 'elektřina',
          '502.2' => 'plyn',
          '502.3' => 'voda',
          '51' => 'služby',
          '511' => 'opravy a údržba',
          '512' => 'cestovné',
          '513' => 'repre',
          '518' => 'ostatní služby',
          '518.1' => 'spoje',
          '518.1.1' => 'poštovné',
          '518.1.2' => 'telekom. služby a internet',
          '518.2' => 'ubytování',
          '518.3' => 'pronájem',
          '518.3.1' => 'nemovitosti',
          '518.3.2' => 'věci movité',
          '518.4' => 'správa nemovitostí',
          '518.4.1' => 'úklid',
          '518.4.2' => 'ostraha',
          '518.5' => 'přeprava',
          '518.6' => 'výroba a produkce',
          '518.6.1' => 'osvětlení a ozvučení',
          '518.6.2' => 'fotodokumentace',
          '518.6.3' => 'výroba záznamového média',
          '518.6.4' => 'výroba tištěného média',
          '518.6.5' => 'ladění hudebních nástrojů',
          '518.6.6' => 'instalace výstav',
          '518.6.7' => 'architektonický návrh',
          '518.7' => 'autorské honoráře, lektorné',
          '518.8' => 'marketing a propagace',
          '518.8.1' => 'reklama',
          '518.8.2' => 'sponzoring',
          '518.8.3' => 'grafické práce',
          '518.8.4' => 'tisk',
          '518.9' => 'autorské poplatky (OSA)',
          '518.10' => 'pojištění',
          '518.11' => 'poradenství',
          '518.11.1' => 'účetní poradenství',
          '518.11.2' => 'právní poradenství',
          '518.12' => 'náhrady za BYOD',
          '52x' => 'osobní náklady',
          '521' => 'mzdy',
          '521.1' => 'dpp',
          '521.2' => 'ps',
          '522' => 'odměny orgánům',
          '524' => 'zák. soc. pojištění',
          '525' => 'ost. soc. pojištění',
          '526' => 'soc. náklady FO',
          '527' => 'zákonné soc. náklady',
          '528' => 'ostatní soc. náklady',
          '530' => 'daně a poplatky',
          '540' => 'jiné provoz. náklady',
          '541' => 'zůstatková cena prodaného majetku',
          '542' => 'prodaný materiál',
          '543' => 'dary',
          '549' => 'manka a škody',
          '551' => 'odpisy DHM a DHIM',
        ];
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Fin/Views/costs.twig', [
            'domains' => $domains,
            'currencies' => $this->iso4217->getAll(),
            'cost_types' => $cost_types,
        ]);
    }

    public function costs_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.costs', 1);
        $req = $request->getParsedBody();

        $req['user'] = $GLOBALS['_GLUED']['authn']['user_id'];
        $req['id'] = 0;
        $req['_v'] = (int) 1;
        $req['_s'] = 'fin.costs';

        // convert body to object
        $req = json_decode(json_encode((object)$req));
  
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
        
        // load the json schema and validate data against it
        /*
        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);
        */
        
        //if ($result->isValid()) {
            $row = array (
                'c_user_id' => (int)$req->user,
                'c_json' => json_encode($req)
            );
            try { $req->id = $this->utils->sql_insert_with_json('t_fin_costs', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            $payload = $builder->withData((array)$req)->withCode(200)->build();
            return $response->withJson($payload, 200);
        /*
        } else {
            $reseed = $request->getParsedBody();
            $payload = $builder->withValidationReseed($reseed)
                               ->withValidationError($result->getErrors())
                               ->withCode(400)
                               ->build();
            return $response->withJson($payload, 400);
        }
        */
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
                t_fin_accounts.c_json->>'$.color' as 'color',
                t_fin_accounts.c_json->>'$.icon' as 'icon',
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
        $req['user'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
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
        $doc->color = $req['color'];
        $doc->icon = $req['icon'];
        $doc->domain = (int)$req['domain'];
        if (array_key_exists('config', $req) and ($req['config'] != "")) {
          $config = json_decode(trim($req['config']), true);
          if (json_last_error() !== 0) throw new HttpBadRequestException( $request, __('Config contains invalid json.'));
          $doc->config = (object)$config;
        } else { $doc->config = new \stdClass(); }
        if (!array_key_exists('currency', $req)) { $doc->currency = ''; } else {  $doc->currency = $req['currency']; }

        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain

        // load the json schema and validate data against it
        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($doc, $schema);
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

        if (array_key_exists('config', $req) and ($req['config'] != "")) {
          $config = json_decode(trim($req['config']), true);
          if (json_last_error() !== 0) throw new HttpBadRequestException( $request, __('Config contains invalid json.'));
          $req['config'] = $config;
        } else { $req['config'] = new \stdClass(); }
        if (!array_key_exists('currency', $req)) { $req['currency'] = ''; }

        $req['user'] = $GLOBALS['_GLUED']['authn']['user_id'];
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
        $req['user'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $req['id'] = (int)$args['uid'];
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }



    public function trx_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.accounts', 1);
        $req = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        
        $meta['user_id'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $fin = new FinUtils();
        $data = $fin->cash($req['data'], [ 'user_id' => (int)$GLOBALS['_GLUED']['authn']['user_id'] ], $req);
        // convert body to object
        //$req = json_decode(json_encode((object)$req));
  
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        /*
        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
          */
            $row = array (
                //'c_domain_id' => (int)$req->domain, 
                'c_account_id' => (int)$data[0]['account_id'],
                'c_user_id' => (int)$meta['user_id'],
                'c_json' => json_encode($data[0]),
            );
            try { $new_trx_id = $this->utils->sql_insert_with_json('t_fin_trx', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // pokud jsou files, nahrajeme je storem k $new_trx_id a tabulce t_fin_trx
            if (!empty($files['file']) and count($files['file']) > 0) {
                foreach ($files['file'] as $file_index => $newfile) {
                    if ($newfile->getError() === UPLOAD_ERR_OK) {
                        $filename = $newfile->getClientFilename();
                        // ziskame tmp path ktere je privatni vlastnost $newfile, jeste zanorene v Stream, takze nejde normalne precist
                        // vypichneme si stream a pouzijeme na to reflection
                        $stream = $newfile->getStream();
                        $reflectionProperty = new \ReflectionProperty(\Nyholm\Psr7\Stream::class, 'uri');
                        $reflectionProperty->setAccessible(true);
                        $tmp_path = $reflectionProperty->getValue($stream);
                        // zavolame funkci, ktera to vlozi. vysledek je pole dulezitych dat. nove id v tabulce links je $file_object_data['new_id']
                        $file_object_data = $this->stor->internal_create($tmp_path, $newfile, $GLOBALS['_GLUED']['authn']['user_id'], $this->stor->app_tables['fin_trx'], $new_trx_id);
                    }
                }
            }
            
            $payload = $builder->withData((array)$req)->withCode(200)->build();
            return $response->withJson($payload, 200);
       /* } else {
            $reseed = $request->getParsedBody();
            $payload = $builder->withValidationReseed($reseed)
                               ->withValidationError($result->getErrors())
                               ->withCode(400)
                               ->build();
            return $response->withJson($payload, 400);
        }*/
    }

    public function trx_patch(Request $request, Response $response, array $args = []): Response {
        throw new HttpBadRequestException( $request, __('Editing transactions is not yet implemented. Ask your admin for a manual edit.'));
        $builder = new JsonResponseBuilder('fin.trx', 1);
        $payload = $builder->withData((array)$data)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }

    public function trx_delete(Request $request, Response $response, array $args = []): Response {
        throw new HttpBadRequestException( $request, __('Deleting transactions is not yet implemented. Ask your admin for a manual delete.'));
        $builder = new JsonResponseBuilder('fin.trx', 1);
        $payload = $builder->withData((array)$data)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }
}

