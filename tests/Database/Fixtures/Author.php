<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\HasMany;

/** A model on a table the test names. */
class Author extends Model
{
    public static string $table_name = '';

    public static string $book_table = '';

    protected $guarded = [];

    public function getTable(): string
    {
        return static::$table_name;
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }
}
