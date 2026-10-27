<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Relations\BelongsTo;

class PersistedItem extends PersistedModel
{
    protected $touches = ['shelf'];

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(PersistedShelf::class, 'shelf_id');
    }
}
