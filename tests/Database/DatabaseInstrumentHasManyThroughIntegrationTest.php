<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\LazyCollection;

function dbHasManyThroughIntegrationConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

function dbHasManyThroughIntegrationSchema()
{
    return dbHasManyThroughIntegrationConnection()->getSchemaBuilder();
}

function dbHasManyThroughIntegrationCreateSchema()
{
    dbHasManyThroughIntegrationSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->unsignedInteger('country_id');
        $table->string('country_short');
        $table->timestamps();
        $table->softDeletes();
    });

    dbHasManyThroughIntegrationSchema()->create('posts', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('title');
        $table->text('body');
        $table->string('email');
        $table->timestamps();
    });

    dbHasManyThroughIntegrationSchema()->create('countries', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('shortname');
        $table->timestamps();
    });
}

function dbHasManyThroughIntegrationSeedData()
{
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->createMany([
            ['title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com'],
            ['title' => 'Another title', 'body' => 'Another body', 'email' => 'taylorotwell@gmail.com'],
        ]);
}

function dbHasManyThroughIntegrationSeedDataExtended()
{
    $country = HasManyThroughTestCountry::create(['id' => 2, 'name' => 'United Kingdom', 'shortname' => 'uk']);
    $country->users()->create(['id' => 2, 'email' => 'example1@gmail.com', 'country_short' => 'uk'])
        ->posts()->createMany([
            ['title' => 'Example1 title1', 'body' => 'Example1 body1', 'email' => 'example1post1@gmail.com'],
            ['title' => 'Example1 title2', 'body' => 'Example1 body2', 'email' => 'example1post2@gmail.com'],
        ]);
    $country->users()->create(['id' => 3, 'email' => 'example2@gmail.com', 'country_short' => 'uk'])
        ->posts()->createMany([
            ['title' => 'Example2 title1', 'body' => 'Example2 body1', 'email' => 'example2post1@gmail.com'],
            ['title' => 'Example2 title2', 'body' => 'Example2 body2', 'email' => 'example2post2@gmail.com'],
        ]);
    $country->users()->create(['id' => 4, 'email' => 'example3@gmail.com', 'country_short' => 'uk'])
        ->posts()->createMany([
            ['title' => 'Example3 title1', 'body' => 'Example3 body1', 'email' => 'example3post1@gmail.com'],
            ['title' => 'Example3 title2', 'body' => 'Example3 body2', 'email' => 'example3post2@gmail.com'],
        ]);
}

/**
 * Seed data for a default HasManyThrough setup.
 */
function dbHasManyThroughIntegrationSeedDefaultData()
{
    HasManyThroughDefaultTestCountry::create(['id' => 1, 'name' => 'United States of America'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com'])
        ->posts()->createMany([
            ['title' => 'A title', 'body' => 'A body'],
            ['title' => 'Another title', 'body' => 'Another body'],
        ]);
}

/**
 * Drop the default tables.
 */
function dbHasManyThroughIntegrationResetDefault()
{
    dbHasManyThroughIntegrationSchema()->drop('users_default');
    dbHasManyThroughIntegrationSchema()->drop('posts_default');
    dbHasManyThroughIntegrationSchema()->drop('countries_default');
}

/**
 * Migrate tables for classes with a Laravel "default" HasManyThrough setup.
 */
function dbHasManyThroughIntegrationMigrateDefault()
{
    dbHasManyThroughIntegrationSchema()->create('users_default', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->unsignedInteger('has_many_through_default_test_country_id');
        $table->timestamps();
    });

    dbHasManyThroughIntegrationSchema()->create('posts_default', function ($table) {
        $table->increments('id');
        $table->integer('has_many_through_default_test_user_id');
        $table->string('title');
        $table->text('body');
        $table->timestamps();
    });

    dbHasManyThroughIntegrationSchema()->create('countries_default', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbHasManyThroughIntegrationCreateSchema();
});

afterEach(function () {
    dbHasManyThroughIntegrationSchema()->drop('users');
    dbHasManyThroughIntegrationSchema()->drop('posts');
    dbHasManyThroughIntegrationSchema()->drop('countries');
});

test('it loads a has many through relation with custom keys', function () {
    dbHasManyThroughIntegrationSeedData();
    $posts = HasManyThroughTestCountry::first()->posts;

    expect($posts[0]->title)->toBe('A title')
        ->and($posts)->toHaveCount(2);
});

test('it loads a default has many through relation', function () {
    dbHasManyThroughIntegrationMigrateDefault();
    dbHasManyThroughIntegrationSeedDefaultData();

    $posts = HasManyThroughDefaultTestCountry::first()->posts;
    expect($posts[0]->title)->toBe('A title')
        ->and($posts)->toHaveCount(2);

    dbHasManyThroughIntegrationResetDefault();
});

test('it loads a relation with custom intermediate and local key', function () {
    dbHasManyThroughIntegrationSeedData();
    $posts = HasManyThroughIntermediateTestCountry::first()->posts;

    expect($posts[0]->title)->toBe('A title')
        ->and($posts)->toHaveCount(2);
});

test('eager loading a relation with custom intermediate and local key', function () {
    dbHasManyThroughIntegrationSeedData();
    $posts = HasManyThroughIntermediateTestCountry::with('posts')->first()->posts;

    expect($posts[0]->title)->toBe('A title')
        ->and($posts)->toHaveCount(2);
});

test('where has on a relation with custom intermediate and local key', function () {
    dbHasManyThroughIntegrationSeedData();
    $country = HasManyThroughIntermediateTestCountry::whereHas('posts', function ($query) {
        $query->where('title', 'A title');
    })->get();

    expect($country)->toHaveCount(1);
});

test('with where has on a relation with custom intermediate and local key', function () {
    dbHasManyThroughIntegrationSeedData();
    $country = HasManyThroughIntermediateTestCountry::withWhereHas('posts', function ($query) {
        $query->where('title', 'A title');
    })->get();

    expect($country)->toHaveCount(1)
        ->and($country->first()->relationLoaded('posts'))->toBeTrue()
        ->and($country->first()->posts->pluck('title')->unique()->toArray())->toEqual(['A title']);
});

test('find method', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->createMany([
            ['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'title' => 'Another title', 'body' => 'Another body', 'email' => 'taylorotwell@gmail.com'],
        ]);

    $country = HasManyThroughTestCountry::first();
    $post = $country->posts()->find(1);

    expect($post)->not->toBeNull()
        ->and($post->title)->toBe('A title')
        ->and($country->posts()->find([1, 2]))->toHaveCount(2)
        ->and($country->posts()->find(new Collection([1, 2])))->toHaveCount(2);
});

test('find many method', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->createMany([
            ['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'title' => 'Another title', 'body' => 'Another body', 'email' => 'taylorotwell@gmail.com'],
        ]);

    $country = HasManyThroughTestCountry::first();

    expect($country->posts()->findMany([1, 2]))->toHaveCount(2)
        ->and($country->posts()->findMany(new Collection([1, 2])))->toHaveCount(2);
});

test('first or fail throws an exception', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us']);

    HasManyThroughTestCountry::first()->posts()->firstOrFail();
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\HasManyThroughTestPost].');

test('find or fail throws an exception', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us']);

    HasManyThroughTestCountry::first()->posts()->findOrFail(1);
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\HasManyThroughTestPost] 1');

test('find or fail with many throws an exception', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->create(['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com']);

    HasManyThroughTestCountry::first()->posts()->findOrFail([1, 2]);
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\HasManyThroughTestPost] 1, 2');

test('find or fail with many using collection throws an exception', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->create(['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com']);

    HasManyThroughTestCountry::first()->posts()->findOrFail(new Collection([1, 2]));
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\HasManyThroughTestPost] 1, 2');

test('find or method', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->create(['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com']);

    $result = HasManyThroughTestCountry::first()->posts()->findOr(1, fn () => 'callback result');
    expect($result)->toBeInstanceOf(HasManyThroughTestPost::class)
        ->and($result->id)->toBe(1)
        ->and($result->title)->toBe('A title');

    $result = HasManyThroughTestCountry::first()->posts()->findOr(1, ['posts.id'], fn () => 'callback result');
    expect($result)->toBeInstanceOf(HasManyThroughTestPost::class)
        ->and($result->id)->toBe(1)
        ->and($result->title)->toBeNull();

    $result = HasManyThroughTestCountry::first()->posts()->findOr(2, fn () => 'callback result');
    expect($result)->toBe('callback result');
});

test('find or method with many', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->createMany([
            ['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'title' => 'Another title', 'body' => 'Another body', 'email' => 'taylorotwell@gmail.com'],
        ]);

    $result = HasManyThroughTestCountry::first()->posts()->findOr([1, 2], fn () => 'callback result');
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result[0]->id)->toBe(1)
        ->and($result[1]->id)->toBe(2)
        ->and($result[0]->title)->toBe('A title')
        ->and($result[1]->title)->toBe('Another title');

    $result = HasManyThroughTestCountry::first()->posts()->findOr([1, 2], ['posts.id'], fn () => 'callback result');
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result[0]->id)->toBe(1)
        ->and($result[1]->id)->toBe(2)
        ->and($result[0]->title)->toBeNull()
        ->and($result[1]->title)->toBeNull();

    $result = HasManyThroughTestCountry::first()->posts()->findOr([1, 2, 3], fn () => 'callback result');
    expect($result)->toBe('callback result');
});

test('find or method with many using collection', function () {
    HasManyThroughTestCountry::create(['id' => 1, 'name' => 'United States of America', 'shortname' => 'us'])
        ->users()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'country_short' => 'us'])
        ->posts()->createMany([
            ['id' => 1, 'title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com'],
            ['id' => 2, 'title' => 'Another title', 'body' => 'Another body', 'email' => 'taylorotwell@gmail.com'],
        ]);

    $result = HasManyThroughTestCountry::first()->posts()->findOr(new Collection([1, 2]), fn () => 'callback result');
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result[0]->id)->toBe(1)
        ->and($result[1]->id)->toBe(2)
        ->and($result[0]->title)->toBe('A title')
        ->and($result[1]->title)->toBe('Another title');

    $result = HasManyThroughTestCountry::first()->posts()->findOr(new Collection([1, 2]), ['posts.id'], fn () => 'callback result');
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result[0]->id)->toBe(1)
        ->and($result[1]->id)->toBe(2)
        ->and($result[0]->title)->toBeNull()
        ->and($result[1]->title)->toBeNull();

    $result = HasManyThroughTestCountry::first()->posts()->findOr(new Collection([1, 2, 3]), fn () => 'callback result');
    expect($result)->toBe('callback result');
});

test('first retrieves first record', function () {
    dbHasManyThroughIntegrationSeedData();
    $post = HasManyThroughTestCountry::first()->posts()->first();

    expect($post)->not->toBeNull()
        ->and($post->title)->toBe('A title');
});

test('all columns are retrieved by default', function () {
    dbHasManyThroughIntegrationSeedData();
    $post = HasManyThroughTestCountry::first()->posts()->first();
    expect(array_keys($post->getAttributes()))->toEqual([
        'id',
        'user_id',
        'title',
        'body',
        'email',
        'created_at',
        'updated_at',
        'laravel_through_key',
    ]);
});

test('only proper columns are selected if provided', function () {
    dbHasManyThroughIntegrationSeedData();
    $post = HasManyThroughTestCountry::first()->posts()->first(['title', 'body']);

    expect(array_keys($post->getAttributes()))->toEqual([
        'title',
        'body',
        'laravel_through_key',
    ]);
});

test('chunk returns correct models', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $country->posts()->chunk(10, function ($postsChunk) {
        $post = $postsChunk->first();
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    });
});

test('chunk by id', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $i = 0;
    $count = 0;

    $country->posts()->chunkById(2, function ($collection) use (&$i, &$count) {
        $i++;
        $count += $collection->count();
    });

    expect($i)->toEqual(3)
        ->and($count)->toEqual(6);
});

test('cursor returns correct models', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $posts = $country->posts()->cursor();

    expect($posts)->toBeInstanceOf(LazyCollection::class);

    foreach ($posts as $post) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    }
});

test('each returns correct models', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $country->posts()->each(function ($post) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    });
});

test('each by id returns correct models', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $country->posts()->eachById(function ($post) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    });
});

test('lazy returns correct models', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $country->posts()->lazy(10)->each(function ($post) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    });
});

test('lazy by id', function () {
    dbHasManyThroughIntegrationSeedData();
    dbHasManyThroughIntegrationSeedDataExtended();
    $country = HasManyThroughTestCountry::find(2);

    $i = 0;

    $country->posts()->lazyById(2)->each(function ($post) use (&$i, &$count) {
        $i++;

        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key',
        ], array_keys($post->getAttributes()));
    });

    expect($i)->toEqual(6);
});

test('intermediate soft deletes are ignored', function () {
    dbHasManyThroughIntegrationSeedData();
    HasManyThroughSoftDeletesTestUser::first()->delete();

    $posts = HasManyThroughSoftDeletesTestCountry::first()->posts;

    expect($posts[0]->title)->toBe('A title')
        ->and($posts)->toHaveCount(2);
});

test('eager loading loads related models correctly', function () {
    dbHasManyThroughIntegrationSeedData();
    $country = HasManyThroughSoftDeletesTestCountry::with('posts')->first();

    expect($country->shortname)->toBe('us')
        ->and($country->posts[0]->title)->toBe('A title')
        ->and($country->posts)->toHaveCount(2);
});

/**
 * Instrument Models...
 */
class HasManyThroughTestUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(HasManyThroughTestPost::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class HasManyThroughTestPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasManyThroughTestUser::class, 'user_id');
    }
}

class HasManyThroughTestCountry extends Instrument
{
    protected $table = 'countries';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasManyThrough(HasManyThroughTestPost::class, HasManyThroughTestUser::class, 'country_id', 'user_id');
    }

    public function users()
    {
        return $this->hasMany(HasManyThroughTestUser::class, 'country_id');
    }
}

/**
 * Instrument Models...
 */
class HasManyThroughDefaultTestUser extends Instrument
{
    protected $table = 'users_default';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(HasManyThroughDefaultTestPost::class);
    }
}

/**
 * Instrument Models...
 */
class HasManyThroughDefaultTestPost extends Instrument
{
    protected $table = 'posts_default';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasManyThroughDefaultTestUser::class);
    }
}

class HasManyThroughDefaultTestCountry extends Instrument
{
    protected $table = 'countries_default';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasManyThrough(HasManyThroughDefaultTestPost::class, HasManyThroughDefaultTestUser::class);
    }

    public function users()
    {
        return $this->hasMany(HasManyThroughDefaultTestUser::class);
    }
}

class HasManyThroughIntermediateTestCountry extends Instrument
{
    protected $table = 'countries';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasManyThrough(HasManyThroughTestPost::class, HasManyThroughTestUser::class, 'country_short', 'email', 'shortname', 'email');
    }

    public function users()
    {
        return $this->hasMany(HasManyThroughTestUser::class, 'country_id');
    }
}

class HasManyThroughSoftDeletesTestUser extends Instrument
{
    use SoftDeletes;

    protected $table = 'users';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(HasManyThroughSoftDeletesTestPost::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class HasManyThroughSoftDeletesTestPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasManyThroughSoftDeletesTestUser::class, 'user_id');
    }
}

class HasManyThroughSoftDeletesTestCountry extends Instrument
{
    protected $table = 'countries';
    protected $guarded = [];

    public function posts()
    {
        return $this->hasManyThrough(HasManyThroughSoftDeletesTestPost::class, HasManyThroughTestUser::class, 'country_id', 'user_id');
    }

    public function users()
    {
        return $this->hasMany(HasManyThroughSoftDeletesTestUser::class, 'country_id');
    }
}
