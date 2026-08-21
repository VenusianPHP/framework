<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model;

class NoTableName extends Model
{
    protected $guarded = [];
    public $timestamps = false;
}
