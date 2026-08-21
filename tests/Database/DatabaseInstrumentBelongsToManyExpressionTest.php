<?php

namespace Tests\Database;

use Exception;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\MorphToMany;
use Voyager\Database\Query\Expression;
use Voyager\Database\Schema\Blueprint;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmExpressionConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmExpressionSchema()
{
    return dbBtmExpressionConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmExpressionCreateSchema()
{
    dbBtmExpressionSchema()->create('posts', fn (Blueprint $t) => $t->id());
    dbBtmExpressionSchema()->create('tags', fn (Blueprint $t) => $t->id());
    dbBtmExpressionSchema()->create('taggables', function (Blueprint $t) {
        $t->unsignedBigInteger('tag_id');
        $t->unsignedBigInteger('taggable_id');
        $t->string('type', 10);
        $t->string('taggable_type');
    }
    );
}

/**
 * Helpers...
 */
function dbBtmExpressionSeedData(): void
{
    $p1 = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->create();
    $p2 = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->create();
    $t1 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();
    $t2 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();
    $t3 = DatabaseInstrumentBelongsToManyExpressionTestTestTag::query()->create();

    $p1->tags()->sync([
        $t1->getKey() => ['type' => 't1'],
        $t2->getKey() => ['type' => 't2'],
    ]);
    $p2->tags()->sync([
        $t2->getKey() => ['type' => 't2'],
        $t3->getKey() => ['type' => 't3'],
    ]);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbBtmExpressionCreateSchema();
});

afterEach(function () {
    dbBtmExpressionSchema()->drop('posts');
    dbBtmExpressionSchema()->drop('tags');
    dbBtmExpressionSchema()->drop('taggables');
});

test('ambiguous columns expression', function () {
    dbBtmExpressionSeedData();

    $tags = DatabaseInstrumentBelongsToManyExpressionTestTestPost::findOrFail(1)
        ->tags()
        ->wherePivotNotIn(new Expression("tag_id || '_' || type"), ['1_t1'])
        ->get();

    expect($tags)->toHaveCount(1);
    expect($tags->first()->getKey())->toEqual(2);
});

test('qualified column expression', function () {
    dbBtmExpressionSeedData();

    $tags = DatabaseInstrumentBelongsToManyExpressionTestTestPost::findOrFail(2)
        ->tags()
        ->wherePivotNotIn(new Expression("taggables.tag_id || '_' || taggables.type"), ['2_t2'])
        ->get();

    expect($tags)->toHaveCount(1);
    expect($tags->first()->getKey())->toEqual(3);
});

test('global scopes are applied to belongs to many relation', function () {
    dbBtmExpressionSeedData();
    $post = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->firstOrFail();
    DatabaseInstrumentBelongsToManyExpressionTestTestTag::addGlobalScope(
        'default',
        static fn () => throw new Exception('Default global scope.')
    );

    $post->tags()->get();
})->throws(Exception::class, 'Default global scope.');

test('global scopes can be removed from belongs to many relation', function () {
    dbBtmExpressionSeedData();
    $post = DatabaseInstrumentBelongsToManyExpressionTestTestPost::query()->firstOrFail();
    DatabaseInstrumentBelongsToManyExpressionTestTestTag::addGlobalScope(
        'default',
        static fn () => throw new Exception('Default global scope.')
    );

    expect($post->tags()->withoutGlobalScopes()->get())->not->toBeEmpty();
});

class DatabaseInstrumentBelongsToManyExpressionTestTestPost extends Instrument
{
    protected $table = 'posts';
    protected $fillable = ['id'];
    public $timestamps = false;

    public function tags(): MorphToMany
    {
        return  $this->morphToMany(
            DatabaseInstrumentBelongsToManyExpressionTestTestTag::class,
            'taggable',
            'taggables',
            'taggable_id',
            'tag_id',
            'id',
            'id',
        );
    }
}

class DatabaseInstrumentBelongsToManyExpressionTestTestTag extends Instrument
{
    protected $table = 'tags';
    protected $fillable = ['id'];
    public $timestamps = false;
}
