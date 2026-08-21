<?php

declare(strict_types=1);

namespace Tests\Database\Pruning\Models;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Prunable;

class PrunableTestModelWithoutPrunableRecords extends Model
{
    use Prunable;

    public function pruneAll()
    {
        return 0;
    }
}
