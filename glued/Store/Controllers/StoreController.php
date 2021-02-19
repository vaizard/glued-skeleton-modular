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


    // ==========================================================
    // SELLERS
    // ==========================================================

    private function sellers_get_sql(array $args = []): array {
      $uid = $args['uid'] ?? null;
      $data = [];

      // Merge the t_store_sellers.c_json with a json object computed
      // out of files marked in t_stor_links as belonging to each of
      // t_store_sellers rows.
      $merge_files =  "JSON_MERGE( 
                  t_store_sellers.c_json, 
                  JSON_OBJECT( 
                    'files', 
                    JSON_ARRAYAGG(  
                      JSON_OBJECT( 'name', t_stor_links.c_filename, 'uri', CONCAT('/stor/get/', t_stor_links.c_uid ) )
                    )
                  )
                )";

      // If $args['uid'] is set, select only this one row. Furhter on,
      // the single row branch of the code is prepended by if ($uid).
      $cond = ($uid > 0) ? "AND t_store_sellers.c_uid = ?" : "";

      // TODO add `WHERE t_store_sellers.c_uid IN (<domains-accessed-by-user>)
      $query = "
        SELECT $merge_files FROM t_store_sellers LEFT JOIN t_stor_links
        ON (t_store_sellers.c_uid = t_stor_links.c_inherit_object)
        WHERE (t_stor_links.c_inherit_object IS NOT NULL $cond)
        GROUP BY t_store_sellers.c_uid
        UNION
        SELECT t_store_sellers.c_json FROM t_store_sellers LEFT JOIN t_stor_links
        ON (t_store_sellers.c_uid = t_stor_links.c_inherit_object)
        WHERE (t_stor_links.c_inherit_object IS NULL)";
        
      if ($uid) $result = $this->db->rawQuery($query, [(int)$uid]);
      else $result = $this->db->rawQuery($query);

      // Rename $key to integers
      if ($result) {
        $key = array_keys($result[0])[0];
        foreach ($result as $obj) $data[] = json_decode($obj[$key]);
      }
      
      // Unnest if returning only a single line
      if ($uid) $data = (array)$data[0];
      return $data;
    }

    /**
     * gets the whole collection or a single object defined by $args['uid']
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     * @return Response
     */

    public function sellers_get_api(Request $request, Response $response, array $args = []): Response {
      $data = $this->sellers_get_sql($args);
      $builder = new JsonResponseBuilder('store.sellers', 1);
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function sellers_get_app(Request $request, Response $response, array $args = []): Response {
        $uid = $args['uid'] ?? null;
        $domains = $this->db->get('t_core_domains');
        if ($uid) {
            return $this->render($response, 'Store/Views/seller.twig', [
                'domains' => $domains,
                'data' => $this->sellers_get_sql($args),
            ]);          
        }
        return $this->render($response, 'Store/Views/sellers.twig', [
            'domains' => $domains,
        ]);
    }


    public function sellers_post_api(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('store.sellers', 1);
        $req = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        
        $data['_s'] = 'store.sellers';
        $data['_v'] = '1';
        $meta['user_id'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $meta['domain'] = $req['domain'];

        $data['business']['name'] = $req['business_name'];
        $data['business']['regid'] = $req['business_regid'];
        $data['business']['vatid'] = $req['business_vatid'];
        $data['business']['vatpayer'] = $req['business_vatpayer'] ?? 0 ? 1 : 0;
        $data['business']['addr'] = $req['business_addr'];

        $data['contacts'] = $req['contacts'];
        $data['template'] = $req['template'];
        $data['uri'] = $req['uri'];


        // TODO load the json schema and validate data against it
        /*
        $loader = new JSL("schema://store/", [ __ROOT__ . "/glued/Store/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://store/sellers.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
          */
            $row = array (
                'c_domain_id' => (int)$meta['domain'],
                'c_user_id' => (int)$meta['user_id'],
                'c_json' => json_encode($data),
                'c_attr' => '{}',
            );
            try { $new_seller_id = $this->utils->sql_insert_with_json('t_store_sellers', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // pokud jsou files, nahrajeme je storem k $new_seller_id a tabulce t_store_sellers
            foreach (['filetc', 'filepp', 'filecr'] as $ftype) {
                if (!empty($files[$ftype]) and count($files[$ftype]) > 0) {
                    foreach ($files[$ftype] as $newfile) {
                        $r = $this->stor->internal_upload($newfile, 'store_sellers', $new_seller_id);
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


    // ==========================================================
    // ITEMS
    // ==========================================================
    // 
    public function items_get_app(Request $request, Response $response, array $args = []): Response {
        $uid = $args['uid'] ?? null;
        $sellers = $this->db->get('t_store_sellers', null, ['c_uid as id', 'c_json->>"$.business.name" as name']);

        if ($uid) {
            return $this->render($response, 'Store/Views/item.twig', [
                'sellers' => $sellers,
                'data' => $this->sellers_get_sql($args),
                'currencies' => $this->iso4217->getAll()
            ]);          
        }
        return $this->render($response, 'Store/Views/items.twig', [
            'sellers' => $sellers,
            'currencies' => $this->iso4217->getAll()
        ]);
    }

    private function items_get_sql(array $args = []): array {
      $uid = $args['uid'] ?? null;
      $data = [];

      // Merge the t_store_sellers.c_json with a json object computed
      // out of files marked in t_stor_links as belonging to each of
      // t_store_sellers rows.
      $merge_files =  "JSON_MERGE( 
                  t_store_items.c_json, 
                  JSON_OBJECT( 
                    'files', 
                    JSON_ARRAYAGG(  
                      JSON_OBJECT( 'name', t_stor_links.c_filename, 'uri', CONCAT('/stor/get/', t_stor_links.c_uid ) )
                    )
                  )
                )";

      // If $args['uid'] is set, select only this one row. Furhter on,
      // the single row branch of the code is prepended by if ($uid).
      $cond = ($uid > 0) ? "AND t_store_items.c_uid = ?" : "";

      // TODO add `WHERE t_store_items.c_uid IN (<domains-accessed-by-user>)
      $query = "
        SELECT $merge_files FROM t_store_items LEFT JOIN t_stor_links
        ON (t_store_items.c_uid = t_stor_links.c_inherit_object)
        WHERE (t_stor_links.c_inherit_object IS NOT NULL $cond)
        GROUP BY t_store_items.c_uid
        UNION
        SELECT t_store_items.c_json FROM t_store_items LEFT JOIN t_stor_links
        ON (t_store_items.c_uid = t_stor_links.c_inherit_object)
        WHERE (t_stor_links.c_inherit_object IS NULL $cond)";
        
      if ($uid) $result = $this->db->rawQuery($query, [(int)$uid]);
      else $result = $this->db->rawQuery($query);

      // Rename $key to integers
      if ($result) {
        $key = array_keys($result[0])[0];
        foreach ($result as $obj) $data[] = json_decode($obj[$key]);
      }
      
      // Unnest if returning only a single line
      if ($uid) $data = (array)$data[0];
      return $data;
    }

    /**
     * gets the whole collection or a single object defined by $args['uid']
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     * @return Response
     */

    public function items_get_api(Request $request, Response $response, array $args = []): Response {
      $data = $this->items_get_sql($args);
      $builder = new JsonResponseBuilder('store.items', 1);
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);
    }

    public function items_post_api(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('store.items', 1);
        $req = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        
        $data['_s'] = 'store.items';
        $data['_v'] = '1';
        $meta['user_id'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $meta['seller'] = $req['seller'];

        $data['info'] = $req['info'];
        $data['buy']  = $req['buy'];
        $data['sell'] = $req['sell'];
        $data['refs'] = $req['refs'];

        print_r($req); die();



        // TODO load the json schema and validate data against it
        /*
        $loader = new JSL("schema://store/", [ __ROOT__ . "/glued/Store/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://store/items.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
          */
            $row = array (
                'c_domain_id' => (int)$meta['domain'],
                'c_user_id' => (int)$meta['user_id'],
                'c_json' => json_encode($data),
                'c_attr' => '{}',
            );
            try { $new_seller_id = $this->utils->sql_insert_with_json('t_store_items', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // pokud jsou files, nahrajeme je storem k $new_seller_id a tabulce t_store_items
            foreach (['filetc', 'filepp', 'filecr'] as $ftype) {
                if (!empty($files[$ftype]) and count($files[$ftype]) > 0) {
                    foreach ($files[$ftype] as $newfile) {
                        $r = $this->stor->internal_upload($newfile, 'store_items', $new_seller_id);
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


    // ==========================================================
    // ACCOUNTS API
    // ==========================================================

   
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

}

