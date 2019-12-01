<?php
declare(strict_types=1);
namespace Glued\Core\Classes\Validation\Rules;

use Glued\Core\Controllers\AbstractController as c;
use Respect\Validation\Rules\AbstractRule;

class EmailAvailable extends AbstractRule
{

    protected $c;
    public function __construct($c) 
    {
        $this->c = $c;
    }
    // ze input bude ten email zajistuje konstrukce validatoru, protoze je to prirazeno jako pravidlo pro email
    public function validate($input)
    {
        //$this->container->db->where('c_type', 1);
        //$this->c->db->where('c_type', 1);
        
        $this->db->where('c_email', $input);
        if ($this->db->getOne("t_core_users")) {
            return false; // validation failed, e-mail already fonud in database
        } else { 
            return true; // validation passed, e-mail can be used to set up a new account
        }
    }
}