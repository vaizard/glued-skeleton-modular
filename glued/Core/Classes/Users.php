<?php

namespace Glued\Core\Classes\Users;

class Users

{
    // pro pouziti containeru ve funkcich teto tridy
    protected $container;

    public function __construct($container) {
        $this->container = $container;
    }

    public function get($user_id) {
        $this->container->db->where("c_uid", $user_id);
        return $this->container->db->getOne("t_core_users");
    }
}
