<?php

namespace Tests\System\Stubs;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;

class ProductStub extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];
}
