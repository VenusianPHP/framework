<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\SoftDeletes;

class PersistedTrashable extends PersistedModel
{
    use SoftDeletes;
}
