<?php

namespace Glued\Core\Classes;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;


class Auth

{
    // pro pouziti containeru ve funkcich teto tridy
    
    protected $db;
    protected $request;

    public function __construct($db, $request) {
        $this->db = $db;
        $this->request = $request;
    }

    public function check() {
        return true ;
    }



public function create($email, $name, $pass, $lang)
{
} 

/*
INSERT INTO `t_core_authn` (`c_type`, `c_user_uid`, `c_hash`, `c_attr`, `c_ts_created`, `c_ts_modified`)
VALUES ('1', '3', md5('w'), NULL, now(), now());

INSERT INTO `t_core_authn` (`c_type`, `c_user_uid`, `c_hash`, `c_attr`, `c_ts_created`, `c_ts_modified`)
VALUES ('1', (SELECT c_uid FROM t_core_users WHERE c_email = 'p@e.org'), md5('w'), NULL, now(), now());


    public function get($uid) {
        
        if (!(v::intVal()->positive()->between(0, 4294967295)->validate($uid))) {
            throw new HttpBadRequestException($this->request, 'Expected value: positive integer');
        }

// select * from t_core_users left join t_core_authn on t_core_users.c_uid = t_core_authn.c_users_uid;

/*
// https://github.com/ThingEngineer/PHP-MySQLi-Database-Class
$this->db->join("t_core_authn a", "a.c_users_uid=u.c_uid", "LEFT");
$this->db->joinWhere("t_core_authn a", "u.c_email", 'pavel@vaizard.org');
$result = $this->db->get ("t_core_users u", null);
    echo "Last executed query was ". $db->getLastQuery();


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




        $this->db->where("c_uid", $uid);
        $result = $this->db->getOne("t_core_users");

        if(!$result) {
            throw new HttpNotFoundException($this->request, 'User not found');
        }

        return $result;
    }


    public function list() {
        // replace with attribute filtering
        // $this->db->where("c_uid", $user_id); 
        return $this->db->get("t_core_users");
    }

}
