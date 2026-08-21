<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmEachByIdConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmEachByIdSchema()
{
    return dbBtmEachByIdConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmEachByIdCreateSchema()
{
    dbBtmEachByIdSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
    });

    dbBtmEachByIdSchema()->create('articles', function ($table) {
        $table->increments('id');
        $table->string('title');
    });

    dbBtmEachByIdSchema()->create('article_user', function ($table) {
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
function dbBtmEachByIdSeedData()
{
    $user = BelongsToManyEachByIdTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    BelongsToManyEachByIdTestTestArticle::query()->insert([
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

    dbBtmEachByIdCreateSchema();
});

afterEach(function () {
    dbBtmEachByIdSchema()->drop('users');
    dbBtmEachByIdSchema()->drop('articles');
    dbBtmEachByIdSchema()->drop('article_user');
});

test('belongs to each by id', function () {
    dbBtmEachByIdSeedData();

    $user = BelongsToManyEachByIdTestTestUser::query()->first();
    $i = 0;

    $user->articles()->eachById(function (BelongsToManyEachByIdTestTestArticle $model) use (&$i) {
        $i++;
        expect($model->id)->toEqual($i);
    });

    expect($i)->toBe(3);
});

class BelongsToManyEachByIdTestTestUser extends Instrument
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];
    public $timestamps = false;

    public function articles()
    {
        return $this->belongsToMany(BelongsToManyEachByIdTestTestArticle::class, 'article_user', 'user_id', 'article_id');
    }
}

class BelongsToManyEachByIdTestTestArticle extends Instrument
{
    protected $table = 'articles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id', 'title'];
}
