<?php

declare(strict_types=1);

namespace Venusian\Tests\Database\Pruning\Models;

use Voyager\Database\Instrument\MassPrunable;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Events\ModelsPruned;

class PrunableTestModelWithPrunableRecords extends Model
{
    use MassPrunable;

    protected $table = 'prunables';
    protected $connection = 'default';

    public function pruneAll()
    {
        signal(new ModelsPruned(static::class, 10));
        signal(new ModelsPruned(static::class, 20));

        return 20;
    }

    public function prunable()
    {
        return static::where('value', '>=', 3);
    }
}
