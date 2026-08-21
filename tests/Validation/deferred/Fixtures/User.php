<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model as Instrument;

class User extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
}
