<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model as Instrument;

class UserWithPrefixedTable extends Instrument
{
    protected $table = 'public.users';
    protected $guarded = [];
    public $timestamps = false;
}
