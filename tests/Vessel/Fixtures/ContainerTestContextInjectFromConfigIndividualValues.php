<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectFromConfigIndividualValues
{
    public $username;
    public $password;
    public $alias = null;

    public function __construct($username, $password, $alias = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->alias = $alias;
    }
}
