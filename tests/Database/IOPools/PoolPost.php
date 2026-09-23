<?php

namespace Venusian\Tests\Database\IOPools;

use Voyager\Database\Instrument\Model;

class PoolPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}
