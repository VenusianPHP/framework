<?php

declare(strict_types=1);

namespace Tests\Database\Pruning\Models;

use Voyager\Database\Instrument\MassPrunable;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;

class PrunableTestSoftDeletedModelWithPrunableRecords extends Model
{
    use MassPrunable, SoftDeletes;

    protected $table = 'prunables';
    protected $connection = 'default';

    public function prunable()
    {
        return static::where('value', '>=', 3);
    }
}
