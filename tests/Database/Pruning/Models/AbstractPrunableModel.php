<?php

declare(strict_types=1);

namespace Tests\Database\Pruning\Models;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Prunable;

abstract class AbstractPrunableModel extends Model
{
    use Prunable;
}
