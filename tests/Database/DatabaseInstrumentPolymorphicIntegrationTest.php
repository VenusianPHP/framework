<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 */
function dbPolyIntegrationConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 */
function dbPolyIntegrationSchema()
{
    return dbPolyIntegrationConnection()->getSchemaBuilder();
}

/**
 * Setup the database schema.
 */
function dbPolyIntegrationCreateSchema(): void
{
    dbPolyIntegrationSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    dbPolyIntegrationSchema()->create('posts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('title');
        $table->text('body');
        $table->timestamps();
    });

    dbPolyIntegrationSchema()->create('comments', function ($table) {
        $table->increments('id');
        $table->integer('commentable_id');
        $table->string('commentable_type');
        $table->integer('user_id');
        $table->text('body');
        $table->timestamps();
    });

    dbPolyIntegrationSchema()->create('likes', function ($table) {
        $table->increments('id');
        $table->integer('likeable_id');
        $table->string('likeable_type');
        $table->timestamps();
    });
}

/**
 * Helpers...
 */
function dbPolyIntegrationSeedData(): void
{
    $taylor = TestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

    $taylor->posts()->create(['title' => 'A title', 'body' => 'A body'])
        ->comments()->create(['body' => 'A comment body', 'user_id' => 1])
        ->likes()->create([]);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbPolyIntegrationCreateSchema();
});

afterEach(function () {
    dbPolyIntegrationSchema()->drop('users');
    dbPolyIntegrationSchema()->drop('posts');
    dbPolyIntegrationSchema()->drop('comments');
});

test('it loads relationships automatically', function () {
    dbPolyIntegrationSeedData();

    $like = TestLikeWithSingleWith::first();

    expect($like->relationLoaded('likeable'))->toBeTrue()
        ->and($like->likeable)->toEqual(TestComment::first());
});

test('it loads chained relationships automatically', function () {
    dbPolyIntegrationSeedData();

    $like = TestLikeWithSingleWith::first();

    expect($like->likeable->relationLoaded('commentable'))->toBeTrue()
        ->and($like->likeable->commentable)->toEqual(TestPost::first());
});

test('it loads nested relationships automatically', function () {
    dbPolyIntegrationSeedData();

    $like = TestLikeWithNestedWith::first();

    expect($like->relationLoaded('likeable'))->toBeTrue()
        ->and($like->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($like->likeable->owner)->toEqual(TestUser::first());
});

test('it loads nested relationships on demand', function () {
    dbPolyIntegrationSeedData();

    $like = TestLike::with('likeable.owner')->first();

    expect($like->relationLoaded('likeable'))->toBeTrue()
        ->and($like->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($like->likeable->owner)->toEqual(TestUser::first());
});

test('it loads nested morph relationships on demand', function () {
    dbPolyIntegrationSeedData();

    TestPost::first()->likes()->create([]);

    $likes = TestLike::with('likeable.owner')->get()->loadMorph('likeable', [
        TestComment::class => ['commentable'],
        TestPost::class => 'comments',
    ]);

    expect($likes[0]->relationLoaded('likeable'))->toBeTrue()
        ->and($likes[0]->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($likes[0]->likeable->relationLoaded('commentable'))->toBeTrue()
        ->and($likes[1]->relationLoaded('likeable'))->toBeTrue()
        ->and($likes[1]->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($likes[1]->likeable->relationLoaded('comments'))->toBeTrue();
});

test('it loads nested morph relationship counts on demand', function () {
    dbPolyIntegrationSeedData();

    TestPost::first()->likes()->create([]);
    TestComment::first()->likes()->create([]);

    $likes = TestLike::with('likeable.owner')->get()->loadMorphCount('likeable', [
        TestComment::class => ['likes'],
        TestPost::class => 'comments',
    ]);

    expect($likes[0]->relationLoaded('likeable'))->toBeTrue()
        ->and($likes[0]->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($likes[0]->likeable->likes_count)->toEqual(2)
        ->and($likes[1]->relationLoaded('likeable'))->toBeTrue()
        ->and($likes[1]->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($likes[1]->likeable->comments_count)->toEqual(1)
        ->and($likes[2]->relationLoaded('likeable'))->toBeTrue()
        ->and($likes[2]->likeable->relationLoaded('owner'))->toBeTrue()
        ->and($likes[2]->likeable->likes_count)->toEqual(2);
});

/**
 * Instrument Models...
 */
class TestUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class TestPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    public function comments()
    {
        return $this->morphMany(TestComment::class, 'commentable');
    }

    public function owner()
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function likes()
    {
        return $this->morphMany(TestLike::class, 'likeable');
    }
}

/**
 * Instrument Models...
 */
class TestComment extends Instrument
{
    protected $table = 'comments';
    protected $guarded = [];
    protected $with = ['commentable'];

    public function owner()
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function likes()
    {
        return $this->morphMany(TestLike::class, 'likeable');
    }
}

class TestLike extends Instrument
{
    protected $table = 'likes';
    protected $guarded = [];

    public function likeable()
    {
        return $this->morphTo();
    }
}

class TestLikeWithSingleWith extends Instrument
{
    protected $table = 'likes';
    protected $guarded = [];
    protected $with = ['likeable'];

    public function likeable()
    {
        return $this->morphTo();
    }
}

class TestLikeWithNestedWith extends Instrument
{
    protected $table = 'likes';
    protected $guarded = [];
    protected $with = ['likeable.owner'];

    public function likeable()
    {
        return $this->morphTo();
    }
}
