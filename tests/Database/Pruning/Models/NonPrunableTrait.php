<?php

declare(strict_types=1);

namespace Venusian\Tests\Database\Pruning\Models;

use Voyager\Database\Instrument\Prunable;

trait NonPrunableTrait
{
    use Prunable;
}
