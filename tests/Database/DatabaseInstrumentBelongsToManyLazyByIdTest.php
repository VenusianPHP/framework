<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmLazyByIdConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmLazyByIdSchema()
{
    return dbBtmLazyByIdConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmLazyByIdCreateSchema()
{
    dbBtmLazyByIdSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
    });

    dbBtmLazyByIdSchema()->create('articles', function ($table) {
        $table->increments('aid');
        $table->string('title');
    });

    dbBtmLazyByIdSchema()->create('article_user', function ($table) {
        $table->integer('article_id')->unsigned();
        $table->foreign('article_id')->references('aid')->on('articles');
        $table->integer('user_id')->unsigned();
        $table->foreign('user_id')->references('id')->on('users');
    });
}

/**
 * Helpers...
 */
function dbBtmLazyByIdSeedData()
{
    $user = BelongsToManyLazyByIdTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    BelongsToManyLazyByIdTestTestArticle::query()->insert([
        ['aid' => 1, 'title' => 'Another title'],
        ['aid' => 2, 'title' => 'Another title'],
        ['aid' => 3, 'title' => 'Another title'],
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

    dbBtmLazyByIdCreateSchema();
});

afterEach(function () {
    dbBtmLazyByIdSchema()->drop('users');
    dbBtmLazyByIdSchema()->drop('articles');
    dbBtmLazyByIdSchema()->drop('article_user');
});

test('belongs to lazy by id', function () {
    dbBtmLazyByIdSeedData();

    $user = BelongsToManyLazyByIdTestTestUser::query()->first();
    $i = 0;

    $user->articles()->lazyById(1)->each(function ($model) use (&$i) {
        $i++;
        expect($model->aid)->toEqual($i);
    });

    expect($i)->toBe(3);
});

class BelongsToManyLazyByIdTestTestUser extends Instrument
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];
    public $timestamps = false;

    public function articles()
    {
        return $this->belongsToMany(BelongsToManyLazyByIdTestTestArticle::class, 'article_user', 'user_id', 'article_id');
    }
}

class BelongsToManyLazyByIdTestTestArticle extends Instrument
{
    protected $primaryKey = 'aid';
    protected $table = 'articles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['aid', 'title'];
}
