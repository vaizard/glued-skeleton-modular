<?php

declare(strict_types=1);

namespace Glued\Store\Controllers;

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
use Glued\Store\Classes\Utils as StoreUtils;


class StoreController extends AbstractTwigController
{
    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */





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
/*
SELECT c_uid2, c_label, c_dt_from from t_contacts_rels where c_uid1 = 29;
SELECT c_uid2, JSON_OBJECT('label', c_label, 'dt_from', c_dt_from) from t_contacts_rels where c_uid1 = 29;
SELECT JSON_OBJECT( c_uid2, JSON_OBJECT('label', c_label, 'dt_from', c_dt_from)) from t_contacts_rels where c_uid1 = 29;
SELECT JSON_OBJECT( c_uid2, JSON_ARRAYAGG(JSON_OBJECT('label', c_label, 'dt_from', c_dt_from))) as relations from t_contacts_rels where t_contacts_rels.c_uid1 = 29 GROUP BY t_contacts_rels.c_uid2 ;

SELECT JSON_OBJECTAGG(t.c_uid2, t.relations)
FROM 
(
    SELECT t_contacts_rels.c_uid2, JSON_ARRAYAGG(JSON_OBJECT('label', c_label, 'dt_from', c_dt_from)) as relations 
    from t_contacts_rels 
    where t_contacts_rels.c_uid1 = 29 
    GROUP BY t_contacts_rels.c_uid2
) AS t;


SELECT c_uid2, JSON_OBJECT( 'uid', c_uid2, 'rel', JSON_ARRAYAGG(JSON_OBJECT('label', c_label, 'dt_from', c_dt_from))) as relations from t_contacts_rels where t_contacts_rels.c_uid1 = 29 GROUP BY t_contacts_rels.c_uid2 ;





 */
      $this->db->orderBy('t_fin_trx.c_trx_dt', 'Desc');
      $this->db->orderBy('t_fin_trx.c_ext_trx_id', 'Desc');
      $this->db->join('t_fin_accounts', 't_fin_trx.c_account_id = t_fin_accounts.c_uid', 'LEFT');
      $json = "JSON_MERGE(t_fin_trx.c_json, JSON_OBJECT('account_name',t_fin_accounts.c_json->>'$.name'), JSON_OBJECT('account_color',t_fin_accounts.c_json->>'$.color'), JSON_OBJECT('account_icon',t_fin_accounts.c_json->>'$.icon'))";
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
        $accounts = $this->db->where('c_json->>"$.type" = \'cash\'')->get('t_fin_accounts', null, ['c_uid as id', 'c_json->>"$.name" as name', 'c_json->>"$.currency" as currency']);
        return $this->render($response, 'Fin/Views/trx.twig', [
            'domains' => $domains,
            'accounts' => $accounts,
            'currencies' => $this->iso4217->getAll()
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
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Fin/Views/costs.twig', [
            'domains' => $domains,
            'currencies' => $this->iso4217->getAll(),
            'cost_types' => $cost_types,
        ]);
    }



    // ==========================================================
    // ACCOUNTS UI
    // ==========================================================

    public function sellers_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Store/Views/sellers.twig', [
            'domains' => $domains,
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



    public function sellers_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('fin.accounts', 1);
        $req = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        
        $data['_s'] = 'store/sellers';
        $data['_v'] = '1';
        $data['user_id'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $data['domain'] = $req['domain'];

        $data['business']['name'] = $req['business_name'];
        $data['business']['regid'] = $req['business_regid'];
        $data['business']['vatid'] = $req['business_vatid'];
        $data['business']['vatpayer'] = $req['business_vatpayer'] ?? 0 ? 1 : 0;
        $data['business']['addr'] = $req['business_addr'];

        $data['contacts'] = $req['contacts'];
        $data['template'] = $req['template'];
        $data['uri'] = $req['uri'];

print_r($data);
print_r($files);
die();
    
        // TODO load the json schema and validate data against it
        /*
        $loader = new JSL("schema://fin/", [ __ROOT__ . "/glued/Fin/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://fin/accounts.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
          */
            $row = array (
                'c_domain_id' => (int)$data['domain'],
                'c_user_id' => (int)$meta['user_id'],
                'c_json' => json_encode($data),
            );
            try { $new_seller_id = $this->utils->sql_insert_with_json('t_store_sellers', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // pokud jsou files, nahrajeme je storem k $new_seller_id a tabulce t_store_sellers
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

