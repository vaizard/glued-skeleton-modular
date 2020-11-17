<?php

namespace Glued\Core\Middleware;

use Glued\Core\Middleware\AbstractMiddleware;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;
use Respect\Validation\Validator as v;

use Casbin\Enforcer;
use Casbin\Util\Log;
use CasbinAdapter\Database\Adapter as DatabaseAdapter;

/**
 * Deals with RBAC/ABAC
 */
final class AuthorizationMiddleware extends AbstractMiddleware implements MiddlewareInterface

{
    private function merge_authn(ServerRequestInterface $request): void {
        $ses = $_SESSION ?? null;
        $jwt = $request->getAttribute($this->settings['auth']['jwt']['attribute']) ?? null;

        $isvalid = true;
        $user_id = null;
        $auth_id = null;

        if (is_array($jwt)) {
            $user_id = $jwt['g_uid'] ?? null;
            $auth_id = $jwt['g_aid'] ?? null;
        }
        if (is_array($ses)) {
            $user_id = $ses['core_user_id'] ?? null;
            $auth_id = $ses['core_auth_id'] ?? null;
        }
        if (is_array($jwt) and is_array($ses)) {
            if (($ses['core_user_id'] ?? null) !== ($jwt['g_uid'] ?? null)) $user_id = null; 
            if (($ses['core_auth_id'] ?? null) !== ($jwt['g_uid'] ?? null)) $user_id = null;
        }

        if (!(v::intVal()->positive()->between(1, 4294967295)->validate($user_id))) $isvalid = false;
        if (!(v::intVal()->positive()->between(1, 4294967295)->validate($auth_id))) $isvalid = false;

        if ($isvalid === true) {
            $GLOBALS['_JWT'] = $jwt;
            $GLOBALS['_GLUED']['authn'] = [
                'success' => null,
                'user_id' => $user_id,
                'auth_id' => $auth_id,
                'object'  => null
            ];
        }
    }


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Set global variables used everywhere
        $this->merge_authn($request);

        if ($this->auth->check() === true) {
            $user_authn = $this->auth->fetch();
            if ($user_authn) {
                $GLOBALS['_GLUED']['authn']['success'] = true;
                $GLOBALS['_GLUED']['authn']['object']  = $user_authn;
                $user_authn['success'] = true;
                $this->view->getEnvironment()->addGlobal('authn', $user_authn);
            }

            $user_authz = $this->auth->get_allowed_actions();
            if ($user_authz) {
                // TODO remove the line below and relevant twig entries once propper authz is in place
                if ($GLOBALS['_GLUED']['authn']['user_id'] == 1) $user_authz['root'] = true;
                $GLOBALS['_GLUED']['authz'] = $user_authz;
                $this->view->getEnvironment()->addGlobal('authz', $user_authz);
            }
        }

        $db = $this->settings['db'];

        $config = [
            'type'     => 'mysql',
            'hostname' => $db['host'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
            'hostport' => '3306',
        ];
        
        $model = __ROOT__ . '/glued/Core/Includes/Casbin/default.model';
        $adapter = DatabaseAdapter::newAdapter($config);
        $adapter = __ROOT__ . '/private/cache/casbin.csv';
        $e = new Enforcer($model, $adapter);
      
        // TODO: we should support auth_id in the enforcing so that users can fine tune access control for different credentials
        $sub = $GLOBALS['_GLUED']['authn']['user_id']; // the user that wants to access a resource.
        $obj = "data1"; // the resource that is going to be accessed.
        $act = "read"; // the operation that the user performs on the resource.

        //r = sub, dom, obj, act
        $rule = [ 'sub', 'domain', 'obj', 'read' ];
        $m = $e->getModel();
        if (!$m->hasPolicy('p', 'p', $rule)) {
            $m->addPolicy('p', 'p', $rule);  
            $e->savePolicy();
        }
                
        // $e->name, or $m->name?
        //print_r( $m->getPolicy(1,1,'all','read') ) ;
        //$e->addRoleForUser('alice', 'admin'); 
        //$e->addPermissionForUser('member', '/foo', 'GET');
        //$e->addPolicy('eve', 'data3', 'read');
        //$e->getRolesForUser('alice');

        return $handler->handle($request);
    }
}

