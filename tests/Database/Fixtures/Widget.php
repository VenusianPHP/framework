<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;

/** The model over the testing traits' fixture table. */
class Widget extends Model
{
    use SoftDeletes;

    protected $table = 'widgets';

    protected $guarded = [];
}
