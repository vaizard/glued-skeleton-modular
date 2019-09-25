<?php

namespace App\Classes;
//namespace Glued\Core\Classes\Users;

class Auth

{
    // pro pouziti containeru ve funkcich teto tridy
    
    protected $container;

    public function __construct($db) {
        $this->db = $db;
    }

    public function check() {
        return true ;
    }

    public function get($user_id) {
        $this->db->where("c_uid", $user_id);
        return $this->db->getOne("t_core_users");
    }
}
