<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmSyncReturnValueTypeConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmSyncReturnValueTypeSchema()
{
    return dbBtmSyncReturnValueTypeConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmSyncReturnValueTypeCreateSchema()
{
    dbBtmSyncReturnValueTypeSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
    });

    dbBtmSyncReturnValueTypeSchema()->create('articles', function ($table) {
        $table->string('id');
        $table->string('title');

        $table->primary('id');
    });

    dbBtmSyncReturnValueTypeSchema()->create('article_user', function ($table) {
        $table->string('article_id');
        $table->foreign('article_id')->references('id')->on('articles');
        $table->integer('user_id')->unsigned();
        $table->foreign('user_id')->references('id')->on('users');
        $table->boolean('visible')->default(false);
    });
}

/**
 * Helpers...
 */
function dbBtmSyncReturnValueTypeSeedData()
{
    BelongsToManySyncTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    BelongsToManySyncTestTestArticle::insert([
        ['id' => '7b7306ae-5a02-46fa-a84c-9538f45c7dd4', 'title' => 'uuid title'],
        ['id' => (string) (PHP_INT_MAX + 1), 'title' => 'Another title'],
        ['id' => '1', 'title' => 'Another title'],
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

    dbBtmSyncReturnValueTypeCreateSchema();
});

afterEach(function () {
    dbBtmSyncReturnValueTypeSchema()->drop('users');
    dbBtmSyncReturnValueTypeSchema()->drop('articles');
    dbBtmSyncReturnValueTypeSchema()->drop('article_user');
});

test('sync return value type', function () {
    dbBtmSyncReturnValueTypeSeedData();

    $user = BelongsToManySyncTestTestUser::query()->first();
    $articleIDs = BelongsToManySyncTestTestArticle::all()->pluck('id')->toArray();

    $changes = $user->articles()->sync($articleIDs);

    collect($changes['attached'])->map(function ($id) {
        expect(gettype($id))->toBe((new BelongsToManySyncTestTestArticle)->getKeyType());
    });

    $user->articles->each(function (BelongsToManySyncTestTestArticle $article) {
        expect((string) $article->pivot->visible)->toBe('0');
    });
});

test('sync with pivot defaults return value type', function () {
    dbBtmSyncReturnValueTypeSeedData();

    $user = BelongsToManySyncTestTestUser::query()->first();
    $articleIDs = BelongsToManySyncTestTestArticle::all()->pluck('id')->toArray();

    $changes = $user->articles()->syncWithPivotValues($articleIDs, ['visible' => true]);

    collect($changes['attached'])->each(function ($id) {
        expect(gettype($id))->toBe((new BelongsToManySyncTestTestArticle)->getKeyType());
    });

    $user->articles->each(function (BelongsToManySyncTestTestArticle $article) {
        expect((string) $article->pivot->visible)->toBe('1');
    });
});

class BelongsToManySyncTestTestUser extends Instrument
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];
    public $timestamps = false;

    public function articles()
    {
        return $this->belongsToMany(BelongsToManySyncTestTestArticle::class, 'article_user', 'user_id', 'article_id')->withPivot('visible');
    }
}

class BelongsToManySyncTestTestArticle extends Instrument
{
    protected $table = 'articles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id', 'title'];
}
