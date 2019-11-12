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

    public function get($uid) {
        
        if (!(v::intVal()->positive()->between(0, 4294967295)->validate($uid))) {
            throw new HttpBadRequestException($this->request, 'Expected value: positive integer');
        }

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
