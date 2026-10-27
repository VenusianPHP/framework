<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Casts\AsUri;

/** A model whose url attribute is cast with AsUri. */
class Bookmark extends Model
{
    protected $table = 'bookmarks';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['url' => AsUri::class];
    }
}
