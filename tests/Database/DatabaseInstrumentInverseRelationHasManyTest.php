<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Voyager\Database\Instrument\Relations\HasMany;
use Voyager\Database\Instrument\Relations\HasOne;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function hasManyInverseConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function hasManyInverseSchema($connection = 'default')
{
    return hasManyInverseConnection($connection)->getSchemaBuilder();
}

function hasManyInverseCreateSchema()
{
    hasManyInverseSchema()->create('test_users', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    hasManyInverseSchema()->create('test_posts', function ($table) {
        $table->increments('id');
        $table->foreignId('user_id');
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

    hasManyInverseCreateSchema();
});

afterEach(function () {
    hasManyInverseSchema()->drop('test_users');
    hasManyInverseSchema()->drop('test_posts');
});

test('has many inverse relation is properly set to parent when lazy loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::all();

    foreach ($users as $user) {
        $this->assertFalse($user->relationLoaded('posts'));
        foreach ($user->posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }
});

test('has many inverse relation is properly set to parent when eager loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::with('posts')->get();

    foreach ($users as $user) {
        $posts = $user->getRelation('posts');

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertSame($user, $post->user);
        }
    }
});

test('has latest of many inverse relation is properly set to parent when lazy loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::all();

    foreach ($users as $user) {
        $this->assertFalse($user->relationLoaded('lastPost'));
        $post = $user->lastPost;

        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has latest of many inverse relation is properly set to parent when eager loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::with('lastPost')->get();

    foreach ($users as $user) {
        $post = $user->getRelation('lastPost');

        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('one of many inverse relation is properly set to parent when lazy loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::all();

    foreach ($users as $user) {
        $this->assertFalse($user->relationLoaded('firstPost'));
        $post = $user->firstPost;

        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('one of many inverse relation is properly set to parent when eager loaded', function () {
    HasManyInverseUserModel::factory()->count(3)->withPosts()->create();
    $users = HasManyInverseUserModel::with('firstPost')->get();

    foreach ($users as $user) {
        $post = $user->getRelation('firstPost');

        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has many inverse relation is properly set to parent when making many', function () {
    $user = HasManyInverseUserModel::create();

    $posts = $user->posts()->makeMany(array_fill(0, 3, []));

    foreach ($posts as $post) {
        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has many inverse relation is properly set to parent when creating many', function () {
    $user = HasManyInverseUserModel::create();

    $posts = $user->posts()->createMany(array_fill(0, 3, []));

    foreach ($posts as $post) {
        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has many inverse relation is properly set to parent when creating many quietly', function () {
    $user = HasManyInverseUserModel::create();

    $posts = $user->posts()->createManyQuietly(array_fill(0, 3, []));

    foreach ($posts as $post) {
        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has many inverse relation is properly set to parent when saving many', function () {
    $user = HasManyInverseUserModel::create();

    $posts = array_fill(0, 3, new HasManyInversePostModel);

    $user->posts()->saveMany($posts);

    foreach ($posts as $post) {
        $this->assertTrue($post->relationLoaded('user'));
        $this->assertSame($user, $post->user);
    }
});

test('has many inverse relation is properly set to parent when updating many', function () {
    $user = HasManyInverseUserModel::create();

    $posts = HasManyInversePostModel::factory()->count(3)->create();

    foreach ($posts as $post) {
        $this->assertTrue($user->isNot($post->user));
    }

    $user->posts()->saveMany($posts);

    foreach ($posts as $post) {
        $this->assertSame($user, $post->user);
    }
});

class HasManyInverseUserModel extends Model
{
    use HasFactory;

    protected $table = 'test_users';
    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new HasManyInverseUserModelFactory();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(HasManyInversePostModel::class, 'user_id')->inverse('user');
    }

    public function lastPost(): HasOne
    {
        return $this->hasOne(HasManyInversePostModel::class, 'user_id')->latestOfMany()->inverse('user');
    }

    public function firstPost(): HasOne
    {
        return $this->posts()->one();
    }
}

class HasManyInverseUserModelFactory extends Factory
{
    protected $model = HasManyInverseUserModel::class;

    public function definition()
    {
        return [];
    }

    public function withPosts(int $count = 3)
    {
        return $this->afterCreating(function (HasManyInverseUserModel $model) use ($count) {
            HasManyInversePostModel::factory()->recycle($model)->count($count)->create();
        });
    }
}

class HasManyInversePostModel extends Model
{
    use HasFactory;

    protected $table = 'test_posts';
    protected $fillable = ['id', 'user_id'];

    protected static function newFactory()
    {
        return new HasManyInversePostModelFactory();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(HasManyInverseUserModel::class, 'user_id');
    }
}

class HasManyInversePostModelFactory extends Factory
{
    protected $model = HasManyInversePostModel::class;

    public function definition()
    {
        return [
            'user_id' => HasManyInverseUserModel::factory(),
        ];
    }
}
