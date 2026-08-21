<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\Pivot as InstrumentPivot;
use Voyager\NutsAndBolts\DataObjects\Carbon;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\ConnectionInterface
 */
function dbBtmSyncTouchesParentConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmSyncTouchesParentSchema()
{
    return dbBtmSyncTouchesParentConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 *
 * @return void
 */
function dbBtmSyncTouchesParentCreateSchema()
{
    dbBtmSyncTouchesParentSchema()->create('articles', function ($table) {
        $table->string('id');
        $table->string('title');

        $table->primary('id');
        $table->timestamps();
    });

    dbBtmSyncTouchesParentSchema()->create('article_user', function ($table) {
        $table->string('article_id');
        $table->foreign('article_id')->references('id')->on('articles');
        $table->integer('user_id')->unsigned();
        $table->foreign('user_id')->references('id')->on('users');
        $table->timestamps();
    });

    dbBtmSyncTouchesParentSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });
}

/**
 * Helpers...
 */
function dbBtmSyncTouchesParentSeedData()
{
    DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
    DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::create(['id' => 2, 'email' => 'anonymous@gmail.com']);
    DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::create(['id' => 3, 'email' => 'anoni-mous@gmail.com']);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbBtmSyncTouchesParentCreateSchema();
});

afterEach(function () {
    dbBtmSyncTouchesParentSchema()->drop('users');
    dbBtmSyncTouchesParentSchema()->drop('articles');
    dbBtmSyncTouchesParentSchema()->drop('article_user');
});

test('sync with detached values should touch', function () {
    dbBtmSyncTouchesParentSeedData();

    Carbon::setTestNow('2021-07-19 10:13:14');
    $article = DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticle::create(['id' => 1, 'title' => 'uuid title']);
    $article->users()->sync([1, 2, 3]);
    expect($article->updated_at->format('Y-m-d H:i:s'))->toBe('2021-07-19 10:13:14');

    Carbon::setTestNow('2021-07-20 19:13:14');
    $result = $article->users()->sync([1, 2]);
    expect(collect($result['detached']))->toHaveCount(1);
    expect((string) collect($result['detached'])->first())->toBe('3');

    $article->refresh();
    expect($article->updated_at->format('Y-m-d H:i:s'))->toBe('2021-07-20 19:13:14');

    $user1 = DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::find(1);
    expect($user1->updated_at->format('Y-m-d H:i:s'))->not->toBe('2021-07-20 19:13:14');
    $user2 = DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::find(2);
    expect($user2->updated_at->format('Y-m-d H:i:s'))->not->toBe('2021-07-20 19:13:14');
    $user3 = DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::find(3);
    expect($user3->updated_at->format('Y-m-d H:i:s'))->not->toBe('2021-07-20 19:13:14');
});

class DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticle extends Instrument
{
    protected $table = 'articles';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'title'];

    public function users()
    {
        return $this
            ->belongsToMany(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticle::class, 'article_user', 'article_id', 'user_id')
            ->using(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticleUser::class)
            ->withTimestamps();
    }
}

class DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticleUser extends InstrumentPivot
{
    protected $table = 'article_user';
    protected $fillable = ['article_id', 'user_id'];
    protected $touches = ['article'];

    public function article()
    {
        return $this->belongsTo(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticle::class, 'article_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser::class, 'user_id', 'id');
    }
}

class DatabaseInstrumentBelongsToManySyncTouchesParentTestTestUser extends Instrument
{
    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'email'];

    public function articles()
    {
        return $this
            ->belongsToMany(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticle::class, 'article_user', 'user_id', 'article_id')
            ->using(DatabaseInstrumentBelongsToManySyncTouchesParentTestTestArticleUser::class)
            ->withTimestamps();
    }
}
