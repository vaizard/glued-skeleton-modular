<?php
namespace Glued\Core\Classes\Auth;
use Respect\Validation\Validator as v;
use UnexpectedValueException;
use ErrorException;

class Auth
{


    protected $db;
    protected $settings;


    public function __construct($db, $settings) {
        $this->db = $db;
        $this->settings = $settings;
    }


    public function user_create($email, $name, $password) {
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
        if ($trx_error === true) { $this->db->rollback(); } 
        else { $this->db->commit(); }
    } 


    public function user_read($uid) { 
        if (!(v::intVal()->positive()->between(0, 4294967295)->validate($uid))) {
            throw new UnexpectedValueException('Bad request (wrong user id).', 550);
        }

        $this->db->where("c_uid", $uid);
        $result = $this->db->getOne("t_core_users");
        if(!$result) {
            throw new UnexpectedValueException('Not found (no such user).', 450);
        }
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


    public function user_list() {
        // replace with attribute filtering
        // $this->db->where("c_uid", $user_id); 
        return $this->db->get("t_core_users");
    }


    /**
     *  attempt to sign in user, return true|false on success or failure   
     */ 
    public function attempt($email, $password) {
        $authenticated = false;
        $this->db->join("t_core_authn a", "a.c_user_uid=u.c_uid", "LEFT");
        $this->db->where("u.c_email", $email);
        $this->db->where("a.c_type", 0); // 0 = passwords, 1 = api keys,
        $result = $this->db->get("t_core_users u", null);
        
        if ($this->db->count > 0) {
            foreach ($result as $user) {
                // TODO: test here if an disabled/old auth password/token are used
                // If yes, then:
                // - log the issue and notify user
                // - ratelimit IP
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

    public function signout() {
        unset($_SESSION['core_user_id']);
        unset($_SESSION['core_auth_id']);
        unset($_SESSION['auth']);
    }


    /**
     * Checks if user is logged in by testing if $_SESSION has core_user_id
     * and core_auth_id set (data set and stored only on the server).
     * @return bool Returns true if user is logged in, false when not
     */
    public function check()
    {
        $user_id = $_SESSION['core_user_id'] ?? false;
        $auth_id = $_SESSION['core_auth_id'] ?? false;
        if ($user_id === false or $auth_id === false) { return false; }
        else { return true; }
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
            signout();
            throw new ErrorException(__('Forbidden and signed out.'), 403);
        }

        return $result;
    }



    public function create($uid, $password) {
        // TODO
        // add interface and function to supplement once user 
        // account with secondary login credentials, such as
        // a secondary (plausible deniability password) or an api
        // key, etc.
    }


    public function delete($uid, $auth_id) {
        // TODO: disable an auth token/password
        // NOTE: do NOT delete disabled aith tokens/passwords.
        // the attempt() function should probe even old passwords
        // to identify IPs that should get rate-limited
    }

    public function update_password($user_id, $auth_id, $password) {
        // TODO add password disabling part (we want to keep the old hash to honeypot bots)
        $this->db->where('c_type', 0);
        $this->db->where('c_uid', (int)$auth_id);
        $this->db->where('c_user_id', (int)$user_id);
        $update = $this->db->update( 't_core_authn', [
            'c_hash' => password_hash($password, $this->settings['php']['password_hash_algo'], $this->settings['php']['password_hash_opts']) 
        ]);
        if(!$update) { return false; } else { return true; }
    }

}
