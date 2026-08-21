<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\MorphMany;
use Voyager\Database\Instrument\Relations\MorphOne;
use Voyager\Database\Instrument\Relations\MorphTo;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function morphManyInverseConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function morphManyInverseSchema($connection = 'default')
{
    return morphManyInverseConnection($connection)->getSchemaBuilder();
}

function morphManyInverseCreateSchema()
{
    morphManyInverseSchema()->create('test_posts', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    morphManyInverseSchema()->create('test_comments', function ($table) {
        $table->increments('id');
        $table->morphs('commentable');
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

    morphManyInverseCreateSchema();
});

afterEach(function () {
    morphManyInverseSchema()->drop('test_posts');
    morphManyInverseSchema()->drop('test_comments');
});

test('morph many inverse relation is properly set to parent when lazy loaded', function () {
    MorphManyInversePostModel::factory()->withComments()->count(3)->create();
    $posts = MorphManyInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('comments'));
        $comments = $post->comments;
        foreach ($comments as $comment) {
            $this->assertTrue($comment->relationLoaded('commentable'));
            $this->assertSame($post, $comment->commentable);
        }
    }
});

test('morph many inverse relation is properly set to parent when eager loaded', function () {
    MorphManyInversePostModel::factory()->withComments()->count(3)->create();
    $posts = MorphManyInversePostModel::with('comments')->get();

    foreach ($posts as $post) {
        $comments = $post->getRelation('comments');

        foreach ($comments as $comment) {
            $this->assertTrue($comment->relationLoaded('commentable'));
            $this->assertSame($post, $comment->commentable);
        }
    }
});

test('morph many guessed inverse relation is properly set to parent when lazy loaded', function () {
    MorphManyInversePostModel::factory()->withComments()->count(3)->create();
    $posts = MorphManyInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('guessedComments'));
        $comments = $post->guessedComments;
        foreach ($comments as $comment) {
            $this->assertTrue($comment->relationLoaded('commentable'));
            $this->assertSame($post, $comment->commentable);
        }
    }
});

test('morph many guessed inverse relation is properly set to parent when eager loaded', function () {
    MorphManyInversePostModel::factory()->withComments()->count(3)->create();
    $posts = MorphManyInversePostModel::with('guessedComments')->get();

    foreach ($posts as $post) {
        $comments = $post->getRelation('guessedComments');

        foreach ($comments as $comment) {
            $this->assertTrue($comment->relationLoaded('commentable'));
            $this->assertSame($post, $comment->commentable);
        }
    }
});

test('morph latest of many inverse relation is properly set to parent when lazy loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('lastComment'));
        $comment = $post->lastComment;

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph latest of many inverse relation is properly set to parent when eager loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::with('lastComment')->get();

    foreach ($posts as $post) {
        $comment = $post->getRelation('lastComment');

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph latest of many guessed inverse relation is properly set to parent when lazy loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('guessedLastComment'));
        $comment = $post->guessedLastComment;

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph latest of many guessed inverse relation is properly set to parent when eager loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::with('guessedLastComment')->get();

    foreach ($posts as $post) {
        $comment = $post->getRelation('guessedLastComment');

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph one of many inverse relation is properly set to parent when lazy loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('firstComment'));
        $comment = $post->firstComment;

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph one of many inverse relation is properly set to parent when eager loaded', function () {
    MorphManyInversePostModel::factory()->count(3)->withComments()->create();
    $posts = MorphManyInversePostModel::with('firstComment')->get();

    foreach ($posts as $post) {
        $comment = $post->getRelation('firstComment');

        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph many inverse relation is properly set to parent when making many', function () {
    $post = MorphManyInversePostModel::create();

    $comments = $post->comments()->makeMany(array_fill(0, 3, []));

    foreach ($comments as $comment) {
        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph many inverse relation is properly set to parent when creating many', function () {
    $post = MorphManyInversePostModel::create();

    $comments = $post->comments()->createMany(array_fill(0, 3, []));

    foreach ($comments as $comment) {
        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph many inverse relation is properly set to parent when creating many quietly', function () {
    $post = MorphManyInversePostModel::create();

    $comments = $post->comments()->createManyQuietly(array_fill(0, 3, []));

    foreach ($comments as $comment) {
        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph many inverse relation is properly set to parent when saving many', function () {
    $post = MorphManyInversePostModel::create();
    $comments = array_fill(0, 3, new MorphManyInverseCommentModel);

    $post->comments()->saveMany($comments);

    foreach ($comments as $comment) {
        $this->assertTrue($comment->relationLoaded('commentable'));
        $this->assertSame($post, $comment->commentable);
    }
});

test('morph many inverse relation is properly set to parent when updating many', function () {
    $post = MorphManyInversePostModel::create();
    $comments = MorphManyInverseCommentModel::factory()->count(3)->create();

    foreach ($comments as $comment) {
        $this->assertTrue($post->isNot($comment->commentable));
    }

    $post->comments()->saveMany($comments);

    foreach ($comments as $comment) {
        $this->assertSame($post, $comment->commentable);
    }
});

class MorphManyInversePostModel extends Model
{
    use HasFactory;

    protected $table = 'test_posts';
    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new MorphManyInversePostModelFactory();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(MorphManyInverseCommentModel::class, 'commentable')->inverse('commentable');
    }

    public function guessedComments(): MorphMany
    {
        return $this->morphMany(MorphManyInverseCommentModel::class, 'commentable')->inverse();
    }

    public function lastComment(): MorphOne
    {
        return $this->morphOne(MorphManyInverseCommentModel::class, 'commentable')->latestOfMany()->inverse('commentable');
    }

    public function guessedLastComment(): MorphOne
    {
        return $this->morphOne(MorphManyInverseCommentModel::class, 'commentable')->latestOfMany()->inverse();
    }

    public function firstComment(): MorphOne
    {
        return $this->comments()->one();
    }
}

class MorphManyInversePostModelFactory extends Factory
{
    protected $model = MorphManyInversePostModel::class;

    public function definition()
    {
        return [];
    }

    public function withComments(int $count = 3)
    {
        return $this->afterCreating(function (MorphManyInversePostModel $model) use ($count) {
            MorphManyInverseCommentModel::factory()->recycle($model)->count($count)->create();
        });
    }
}

class MorphManyInverseCommentModel extends Model
{
    use HasFactory;

    protected $table = 'test_comments';
    protected $fillable = ['id', 'commentable_type', 'commentable_id'];

    protected static function newFactory()
    {
        return new MorphManyInverseCommentModelFactory();
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo('commentable');
    }
}

class MorphManyInverseCommentModelFactory extends Factory
{
    protected $model = MorphManyInverseCommentModel::class;

    public function definition()
    {
        return [
            'commentable_type' => MorphManyInversePostModel::class,
            'commentable_id' => MorphManyInversePostModel::factory(),
        ];
    }
}
