<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model as Instrument;

class NoTableNameModel extends Instrument
{
    protected $guarded = [];
    public $timestamps = false;
}
