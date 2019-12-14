<?php

namespace Glued\Core\Classes\Auth;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Auth

{
    protected $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function check() {
        return true ;
    }

    public function create($email, $name, $pass, $lang) {
    } 

    public function drop($uid) {
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


    /**
     *  attempt to sign in user, return true|false on success or failure   
     */ 
    public function attempt(Request $request, $email, $password)
    {

        $authenticated = false;
        $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
        $this->db->where("u.c_email", $email);
        $this->db->where("a.c_type", 0); // 0 = passwords, 1 = api keys,
        $result = $this->db->get("t_core_users u", null);
        
        if ($this->db->count > 0) {
            foreach ($result as $user) {
                if (password_verify($password, $user['c_hash'])) {
                    $_SESSION['core_user_id'] = $user['c_user_uid'];
                    $_SESSION['core_auth_id'] = $user['c_uid'];
                    $authenticated = true;
                    break;
                }
            }
        }
        return $authenticated;
    }


    public function auth_add(Request $request, $uid, $password) {
        // TODO
        // add interface and function to supplement once user 
        // account with secondary login credentials, such as
        // a secondary (plausible deniability password) or an api
        // key, etc.
    }

    public function auth_drop($uid, $auth_id) {
        // TODO: drop an auth method
    }

    public function get(Request $request, $uid) { // otazka: netahat to pres $this->request, ale mit get($request, $uid)???? <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
        
        if (!(v::intVal()->positive()->between(0, 4294967295)->validate($uid))) {
            throw new HttpBadRequestException($request, 'Expected value: positive integer');
        }

        $this->db->where("c_uid", $uid);
        $result = $this->db->getOne("t_core_users");

        if(!$result) {
            throw new HttpNotFoundException($request, 'User not found');
        }

        return $result;
    }


    public function list(Request $request) {
        // replace with attribute filtering
        // $this->db->where("c_uid", $user_id); 
        return $this->db->get("t_core_users");
    }

}
