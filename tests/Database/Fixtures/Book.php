<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsTo;

/** A model on a table the test names. */
class Book extends Model
{
    public static string $table_name = '';

    protected $guarded = [];

    public function getTable(): string
    {
        return static::$table_name;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }
}
