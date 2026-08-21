<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmChunkByIdConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmChunkByIdSchema()
{
    return dbBtmChunkByIdConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmChunkByIdCreateSchema()
{
    dbBtmChunkByIdSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
    });

    dbBtmChunkByIdSchema()->create('articles', function ($table) {
        $table->increments('id');
        $table->string('title');
    });

    dbBtmChunkByIdSchema()->create('article_user', function ($table) {
        $table->increments('id');
        $table->integer('article_id')->unsigned();
        $table->foreign('article_id')->references('id')->on('articles');
        $table->integer('user_id')->unsigned();
        $table->foreign('user_id')->references('id')->on('users');
    });
}

/**
 * Helpers...
 */
function dbBtmChunkByIdSeedData()
{
    $user = BelongsToManyChunkByIdTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    BelongsToManyChunkByIdTestTestArticle::query()->insert([
        ['id' => 1, 'title' => 'Another title'],
        ['id' => 2, 'title' => 'Another title'],
        ['id' => 3, 'title' => 'Another title'],
    ]);

    $user->articles()->sync([3, 1, 2]);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbBtmChunkByIdCreateSchema();
});

afterEach(function () {
    dbBtmChunkByIdSchema()->drop('users');
    dbBtmChunkByIdSchema()->drop('articles');
    dbBtmChunkByIdSchema()->drop('article_user');
});

test('belongs to chunk by id', function () {
    dbBtmChunkByIdSeedData();

    $user = BelongsToManyChunkByIdTestTestUser::query()->first();
    $i = 0;

    $user->articles()->chunkById(1, function (Collection $collection) use (&$i) {
        $i++;
        expect($collection->first()->id)->toEqual($i);
    });

    expect($i)->toBe(3);
});

test('belongs to chunk by id desc', function () {
    dbBtmChunkByIdSeedData();

    $user = BelongsToManyChunkByIdTestTestUser::query()->first();
    $i = 0;

    $user->articles()->chunkByIdDesc(1, function (Collection $collection) use (&$i) {
        expect($collection->first()->id)->toEqual(3 - $i);
        $i++;
    });

    expect($i)->toBe(3);
});

class BelongsToManyChunkByIdTestTestUser extends Instrument
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];
    public $timestamps = false;

    public function articles()
    {
        return $this->belongsToMany(BelongsToManyChunkByIdTestTestArticle::class, 'article_user', 'user_id', 'article_id');
    }
}

class BelongsToManyChunkByIdTestTestArticle extends Instrument
{
    protected $table = 'articles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id', 'title'];
}
