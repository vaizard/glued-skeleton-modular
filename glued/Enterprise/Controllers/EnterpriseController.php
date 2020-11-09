<?php

declare(strict_types=1);

namespace Glued\Enterprise\Controllers;

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
use Glued\Enterprise\Classes\Utils as EnterpriseUtils;

class EnterpriseController extends AbstractTwigController
{

    // ==========================================================
    // PROJECTS UI
    // ==========================================================

    public function projects_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        $projects = $this->db->get('t_enterprise_projects', null, ['c_uid as id', 'c_json->>"$.name" as name', 'c_json->>"$.description" as description']);
        return $this->render($response, 'Enterprise/Views/projects.twig', [
            'domains' => $domains,
            'projects' => $projects
        ]);
    }


    public function opportunities_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Enterprise/Views/opportunities.twig', [
            'domains' => $domains,
        ]);
    }


    // ==========================================================
    // PROJECTS API
    // ==========================================================

    private function sql_projects_list() {
        $data = $this->db->rawQuery("
            SELECT
                t_enterprise_projects.c_uid as 'id',
                t_enterprise_projects.c_json->>'$._s' as '_s',
                t_enterprise_projects.c_json->>'$._v' as '_v',
                t_enterprise_projects.c_json->>'$.name' as 'name',
                t_enterprise_projects.c_json->>'$.description' as 'description'
            FROM `t_enterprise_projects` 
        ");
        return $data;
    }


    public function projects_list(Request $request, Response $response, array $args = []): Response
    {
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $payload = $builder->withData((array)$this->sql_projects_list())->withCode(200)->build();
        return $response->withJson($payload);
        // TODO handle errors
        // TODO the withData() somehow escapes quotes in t_enterprise_projects.c_json->>'$.config' 
        //      need to figure out where this happens and zap it.
    }


    public function projects_patch(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('enterprise.projects', 1);

        // Get patch data
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];

        // Get old data
        $this->db->where('c_uid', $req['id']);
        $doc = $this->db->getOne('t_enterprise_projects', ['c_json'])['c_json'];
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
        $schema = $loader->loadSchema("schema://fin/projects.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($doc, $schema);
        if ($result->isValid()) {
            $row = [ 'c_json' => json_encode($doc) ];
            $this->db->where('c_uid', $req['id']);
            $id = $this->db->update('t_enterprise_projects', $row);
            if (!$id) { throw new HttpInternalServerErrorException( $request, __('Updating of the account failed.')); }
        } else { throw new HttpBadRequestException( $request, __('Invalid account data.')); }

        // Success
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);  
    }


    public function projects_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $req = $request->getParsedBody();

        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = 0;
        $req['_v'] = (int) 1;
        $req['_s'] = 'enterprise.projects';
        
        $parent = (int) $req['parent'];
        unset($req['parent']);  // protoze neni ve schematu
        
        // convert body to object
        $req = json_decode(json_encode((object)$req));
  
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
    
        // load the json schema and validate data against it
        $loader = new JSL("schema://enterprise/", [ __ROOT__ . "/glued/Enterprise/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://enterprise/projects.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_json' => json_encode($req)
            );
            try { $req->id = $this->utils->sql_insert_with_json('t_enterprise_projects', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // pokud je nastaveny parent > 0, vlozime
            if ($parent > 0) {
                $data = Array (
                "c_parent" => $parent,
                "c_child" => $req->id
                );
                $this->db->insert ('t_enterprise_projects_tree', $data);
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


    public function projects_delete(Request $request, Response $response, array $args = []): Response {
        try { 
          $this->db->where('c_uid', (int)$args['uid']);
          $this->db->delete('t_enterprise_projects');
        } catch (Exception $e) { 
          throw new HttpInternalServerErrorException($request, $e->getMessage());  
        }
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $req = $request->getParsedBody();
        $req['user'] = (int)$_SESSION['core_user_id'];
        $req['id'] = (int)$args['uid'];
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }
}
