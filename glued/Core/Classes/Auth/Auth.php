<?php

declare(strict_types=1);

namespace Glued\Core\Classes\Auth;
use ErrorException;
use Firebase\JWT\JWT;
use Respect\Validation\Validator as v;
use UnexpectedValueException;

/**
 * Authentication
 *
 * Glued's authentication is twofold:
 * 
 * - session based
 * - jwt token based
 *
 * We keep sessions around since we're afraid to deal with the fallout.
 * Some components (i.e. slim-flash) rely on sessions, so we can't
 * get rid of them easily wihtout thinking. Going completely stateless
 * is also quite an endavour (i.e. token invalidation mechanisms, etc.). 
 * We use jwt to get state of the art authentication.
 * 
 * Users using browsers will always get 
 *
 * - a session cookie
 * - a jwt token (stored in a cookie)
 *
 * Users accessing the api directly will get only
 *
 * - the jwt token (sent in the response body)
 *
 * The session authentication middleware is configured to require a valid
 * session on all private routes and all api routes with the exception of
 * the signup and signin page routes. The jwt authentication middleware is 
 * configured to to require a valid jwt token sent as either a header or 
 * a cookie on all api routes with the exception of the signin api route.
 *
 * The middlewares set the $request attributes which are accessible through
 * the $request->getattribute('auth') array. First executes the jwt 
 * middleware, later the session middleware.  
 *
 * TODO: replace $_SESSION everywhere with $request->getattribute('auth')
 * 
 */

class Auth
{

    protected $db;
    protected $settings;

    public function __construct($db, $settings) {
        $this->db = $db;
        $this->settings = $settings;
    }

    //////////////////////////////////////////////////////////////////////////
    // HELPERS ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////


    public function jwt_gettoken (string $sub, array $data = []) : string {
        $now = new \DateTime();
        $exp = new \DateTime('+' . $this->settings['auth']['jwt']['expiry']);
        $payload = [
            'iss'   => $_SERVER['SERVER_NAME'],   // Issuer
            'iat'   => $now->getTimeStamp(),      // Issued at time
            'exp'   => $exp->getTimeStamp(),      // Expires
            'jti'   => uniqid(),
            'sub'   => $sub,
        ];
        $payload = array_merge($payload, $data);
        $token = JWT::encode($payload, $this->settings['auth']['jwt']['secret'], $this->settings['auth']['jwt']['algorithm']);
        return $token;
    }

    public function jwt_setcookie (string $sub, array $data = []) :? string {

        $token = $this->jwt_gettoken($sub, $data);
        // We generally want to sync the validity of the cookie holding the JWT token to the validity
        // of the user session. We additionally want to keep the EXP payoad in the JWT short, 15 minutes
        // max and keep updating it according to user activity (this means we can get a valid JWT cookie
        // containing and expired JWT token).
        // TODO update expired JWT tokens in cookies if users session is still ok.
        if ($this->settings['auth']['session']['lifetime'] == 0) 
            $expires = 0;
        else 
            $expires = (new \DateTime('+' . $this->settings['auth']['jwt']['expiry']))->getTimeStamp();
        $opts = [
            'expires' => $expires,
            'path'     => $this->settings['auth']['cookie']['path'] ?? '/api', //api
            'domain'   => $this->settings['auth']['cookie']['domain'] ?? null,
            'secure'   => $this->settings['auth']['cookie']['secure'],
            'httponly' => $this->settings['auth']['cookie']['httponly'], //false
            'samesite' => $this->settings['auth']['cookie']['samesite'],
        ];
        if (setcookie($this->settings['auth']['jwt']['cookie'], $token, $opts)) return $token; else return null;

    }

    /**
     * [log description]
     * @param  [type] $request [description]
     * @param  [type] $event   [description]
     * @param  [type] $details [description]
     * @return [type]          [description]
     */
    public function log($request, $event, $details) : bool {
        $user_id = $_SESSION['core_user_id'] ?? false;
        $auth_id = $_SESSION['core_auth_id'] ?? false;
        $fingerprint['ua'] = $request->getUserAgent();
        $fingerprint['ua'] = $request->getUserAgent();
        // TODO log successfull and unsuccessful logins and other
        // important events to inform user about nasties. 
        return true;
    }

    //////////////////////////////////////////////////////////////////////////
    // USERS CRUD ////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////

    /**
     * [user_create description]
     * @param  [type] $email    [description]
     * @param  [type] $name     [description]
     * @param  [type] $password [description]
     * @return [type]           [description]
     */
    public function user_create($email, $name, $password) : bool {
        $trx_error = false;
        $this->db->startTransaction();
        $data = array (
            'c_email' => $email,
            'c_name'  => $name,
        );
        if (!$this->db->insert ('t_core_users', $data)) { $trx_error = true; }
        $subq = $this->db->subQuery()->where('c_email', $email)->getOne('t_core_users', 'c_uid');
        $data = array (
            'c_type' => 0,
            'c_user_uid' => $subq,
            'c_hash' => password_hash($password, $this->settings['php']['password_hash_algo'], $this->settings['php']['password_hash_opts']),
        );
        if (!$this->db->insert ('t_core_authn', $data)) { $trx_error = true; }
        if ($trx_error === true) { $this->db->rollback(); return false; } 
        else { 
            if (!$this->db->commit()) { return true; } else { return false; }
        }
    } 

    /**
     * [user_read description]
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function user_read($uid) : array { 
        if (!(v::intVal()->positive()->between(0, 4294967295)->validate($uid)))
            throw new UnexpectedValueException('Bad request (wrong user id).', 550);
        $this->db->where("c_uid", $uid);
        $result = $this->db->getOne("t_core_users");
        if (!$result)
            throw new UnexpectedValueException('Not found (no such user).', 450);
        return $result;
    }

    public function user_delete($uid) {
        /*
        DELETING ACCOUNTS & GDPR:
        - user wants to delete his/her account.
        - we should remove all data from t_core_authn to prevent new logins
        - we should set user's email in t_core_users to null (gdpr compliance)
        - we must keep the user's handle/screenname (so that others cant impersonate the user)
        - we must keep the row in t_core_users (so that data left behind by the user have a valid owner relationship)
        - we must set the attr in t_core_users to disabled, so that we can handle edge cases on application side
        PLAUSIBLE DENIABILITY
        - user may choose to have multiple login/password combinations and/or multiple api keys enabling devices access as well
        - each login/pass and api can have an assigned scope enabling/disabling access to selected data. This way, a user can
          hand out i.e. limited scope access credentials to criminals, or prying law enforcement in non-free regimes. Naturally
          this works only as long as the server instances you host dont get compromised too - this is meant to protect users
          such as investigators or journalists.
        */
    }

    public function user_list() :? array {
        // replace with attribute filtering
        // $this->db->where("c_uid", $user_id); 
        return $this->db->get("t_core_users");
    }

    //////////////////////////////////////////////////////////////////////////
    // AUTHENTICATION ACTIONS ////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////


    /**
     *  attempt to sign in user, returns jwt token string on success or null on failure
     */ 
    public function attempt($email, $password) :? string {
        $token = null;
        $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
        $this->db->where("u.c_email", $email);
        $this->db->where("a.c_type", 0); // 0 = passwords, 1 = api keys,
        $result = $this->db->get("t_core_users u", null);
        
        if ($this->db->count > 0) {
            foreach ($result as $user) {
                // TODO: test here if an disabled/old auth password/token are used
                // If yes, then:
                // - log the issue and notify user
                // - ratelimit IP - or maybe not - sidechannel attack?
                if (password_verify($password, $user['c_hash'])) {
                    // session started by SessionMiddleware
                    $token = $this->jwt_setcookie($email, [ 'g_uid' => $user['c_user_uid'], 'g_aid' => $user['c_uid'] ]);
                    $_SESSION['core_user_id'] = $user['c_user_uid'];
                    $_SESSION['core_auth_id'] = $user['c_uid'];
                    break;
                }
            }
        }
        return $token;
    }


    public function signout() : void {
        // Delete session server side and session cookie client side
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            session_write_close();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                $params['expires'] = time() - 40000;
                unset($params['lifetime']);
                setcookie( session_name(), '', $params) ;
            }
        }

        // Expire jwt token (server side and client side),
        // delete jwt cookie client side
        $params = $this->settings['auth']['cookie'];
        $params['expires'] = time() - 40000;
        setcookie($this->settings['auth']['jwt']['cookie'], "", [
            'expires' => time() - 40000,
            'path' => $this->settings['auth']['cookie']['path'] ?? '/api',
            'domain' => $this->settings['auth']['cookie']['domain'] ?? null,
            'secure'   => $this->settings['auth']['cookie']['secure'],
            'httponly' => $this->settings['auth']['cookie']['httponly'], //false
            'samesite' => $this->settings['auth']['cookie']['samesite'] ?? 'lax',
        ]);
        // TODO add jwt token expiry here (iat, jti) and check against
        // a table of no longer active tokens that still have some time to live
    }


    /**
     * Checks if user is logged in by testing if $_SESSION has core_user_id
     * and core_auth_id set (data set and stored only on the server).
     * @return bool Returns true if user is logged in, false when not
     */
    public function check() : bool  {
        $user_id = $_SESSION['core_user_id'] ?? false;
        $auth_id = $_SESSION['core_auth_id'] ?? false;
        if ($user_id === false or $auth_id === false) { return false; }
        else { return true; }
    }

    /**
     * Checks if user is logged in by testing if the JWT payload has 'g_uid'
     * and 'g_aid' set. The request attributes are assigned by the jwt
     * authorization middleware.
     * @param object $request PSR-7 request object
     * @return bool Returns true if user is logged in, false when not
     */
    public function check_jwt($request) : bool {
        $jwt_attr = $this->settings['auth']['jwt']['attribute'];
        $user_id = $request->getAttribute($jwt_attr)['g_uid'] ?? false;
        $auth_id = $request->getAttribute($jwt_attr)['g_aid'] ?? false;
        if ($user_id === false or $auth_id === false) { return false; }
        else { return true; }
        // TODO appropriatelly add check_jwt() to places where auth->check() is already present.
    }

    public function reset($email)
    {
        $trx_error = false;
        $this->db->startTransaction();
        $data = array (
            'c_email' => $email,
        );
        if (!$this->db->insert ('t_core_users', $data)) { $trx_error = true; }
        $subq = $this->db->subQuery()->where('c_type', 0)->where('c_email', $email)->getOne('t_core_authn', 'c_uid as c_authn_uid, c_user_uid');
        $data = array (
            'c_type' => 0,
            'c_user_uid' => $subq['c_user_uid'],
            'c_user_authn' => $subq['c_authn_uid'],
            'c_token' => random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES),
            'c_ts_'
        );
        if (!$this->db->insert ('t_core_authn', $data)) { $trx_error = true; }
        if ($trx_error === true) { $this->db->rollback(); } 
        else { $this->db->commit(); }
    }


    /**
     * Get response-modifying data.
     * (Auth context, personalization)
     * 
     * @param  [type]  $user_id [description]
     * @param  [type]  $auth_id [description]
     * @return [type]           [description]
     */
    public function response() { 
        $user_id = $_SESSION['core_user_id'] ?? false;
        $auth_id = $_SESSION['core_auth_id'] ?? false;
        if (($user_id === false) or ($auth_id === false)) { return false; }

        if ((!(v::intVal()->positive()->between(0, 4294967295)->validate($user_id))) or (!(v::intVal()->positive()->between(0, 4294967295)->validate($auth_id)))) {
            throw new ErrorException('Internal Server Error (mangled session).', 500);
        }

        $columns = [ "u.c_uid AS u_uid",
                     "u.c_attr AS u_attr",
                     "u.c_email AS u_email",
                     "u.c_name AS u_name",
                     "u.c_lang AS u_lang",
                     "a.c_uid AS a_uid",
                     "a.c_type AS a_type",
                     "a.c_attr AS a_attr" ];
        $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
        $this->db->where("u.c_uid", $user_id);
        $this->db->where("a.c_uid", $auth_id);
        $result = $this->db->getOne("t_core_users u", $columns );

        if(!$result) {
            $this->signout();
            //throw new ErrorException(__('Forbidden and signed out.'), 403);
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////
    // CREDENTIALS CRUD //////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////

    public function cred_create($uid, $password) {
        // TODO
        // add interface and function to supplement once user 
        // account with secondary login credentials, such as
        // a secondary (plausible deniability password) or an api
        // key, etc.
    }

    public function cred_delete($uid, $auth_id) {
        // TODO: disable an auth token/password
        // NOTE: do NOT delete disabled aith tokens/passwords.
        // the attempt() function should probe even old passwords
        // to identify IPs that should get rate-limited
    }

    public function cred_update($user_id, $auth_id, $password) {
        // TODO add password disabling part (we want to keep the old hash to honeypot bots)
        $this->db->where('c_type', 0);
        $this->db->where('c_uid', (int)$auth_id);
        $this->db->where('c_user_uid', (int)$user_id);
        $update = $this->db->update( 't_core_authn', [
            'c_hash' => password_hash($password, $this->settings['php']['password_hash_algo'], $this->settings['php']['password_hash_opts']) 
        ]);
        if (!$update) { return false; } else { return true; }
    }

}
