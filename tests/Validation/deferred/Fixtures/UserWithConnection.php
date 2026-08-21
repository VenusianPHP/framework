<?php

namespace Tests\Validation\deferred\Fixtures;

use Tests\Validation\deferred\Fixtures\User;

class UserWithConnection extends User
{
    protected $connection = 'mysql';
}
