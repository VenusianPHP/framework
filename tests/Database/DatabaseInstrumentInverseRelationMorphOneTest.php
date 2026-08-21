<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\MorphOne;
use Voyager\Database\Instrument\Relations\MorphTo;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function morphOneInverseConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function morphOneInverseSchema($connection = 'default')
{
    return morphOneInverseConnection($connection)->getSchemaBuilder();
}

function morphOneInverseCreateSchema()
{
    morphOneInverseSchema()->create('test_posts', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    morphOneInverseSchema()->create('test_images', function ($table) {
        $table->increments('id');
        $table->morphs('imageable');
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

    morphOneInverseCreateSchema();
});

afterEach(function () {
    morphOneInverseSchema()->drop('test_posts');
    morphOneInverseSchema()->drop('test_images');
});

test('morph one inverse relation is properly set to parent when lazy loaded', function () {
    MorphOneInverseImageModel::factory(6)->create();
    $posts = MorphOneInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('image'));
        $image = $post->image;
        $this->assertTrue($image->relationLoaded('imageable'));
        $this->assertSame($post, $image->imageable);
    }
});

test('morph one inverse relation is properly set to parent when eager loaded', function () {
    MorphOneInverseImageModel::factory(6)->create();
    $posts = MorphOneInversePostModel::with('image')->get();

    foreach ($posts as $post) {
        $image = $post->getRelation('image');

        $this->assertTrue($image->relationLoaded('imageable'));
        $this->assertSame($post, $image->imageable);
    }
});

test('morph one guessed inverse relation is properly set to parent when lazy loaded', function () {
    MorphOneInverseImageModel::factory(6)->create();
    $posts = MorphOneInversePostModel::all();

    foreach ($posts as $post) {
        $this->assertFalse($post->relationLoaded('guessedImage'));
        $image = $post->guessedImage;
        $this->assertTrue($image->relationLoaded('imageable'));
        $this->assertSame($post, $image->imageable);
    }
});

test('morph one guessed inverse relation is properly set to parent when eager loaded', function () {
    MorphOneInverseImageModel::factory(6)->create();
    $posts = MorphOneInversePostModel::with('guessedImage')->get();

    foreach ($posts as $post) {
        $image = $post->getRelation('guessedImage');

        $this->assertTrue($image->relationLoaded('imageable'));
        $this->assertSame($post, $image->imageable);
    }
});

test('morph one inverse relation is properly set to parent when making', function () {
    $post = MorphOneInversePostModel::create();

    $image = $post->image()->make();

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when creating', function () {
    $post = MorphOneInversePostModel::create();

    $image = $post->image()->create();

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when creating quietly', function () {
    $post = MorphOneInversePostModel::create();

    $image = $post->image()->createQuietly();

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when force creating', function () {
    $post = MorphOneInversePostModel::create();

    $image = $post->image()->forceCreate();

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when saving', function () {
    $post = MorphOneInversePostModel::create();
    $image = MorphOneInverseImageModel::make();

    $this->assertFalse($image->relationLoaded('imageable'));
    $post->image()->save($image);

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when saving quietly', function () {
    $post = MorphOneInversePostModel::create();
    $image = MorphOneInverseImageModel::make();

    $this->assertFalse($image->relationLoaded('imageable'));
    $post->image()->saveQuietly($image);

    $this->assertTrue($image->relationLoaded('imageable'));
    $this->assertSame($post, $image->imageable);
});

test('morph one inverse relation is properly set to parent when updating', function () {
    $post = MorphOneInversePostModel::create();
    $image = MorphOneInverseImageModel::factory()->create();

    $this->assertTrue($post->isNot($image->imageable));

    $post->image()->save($image);

    $this->assertTrue($post->is($image->imageable));
    $this->assertSame($post, $image->imageable);
});

class MorphOneInversePostModel extends Model
{
    use HasFactory;

    protected $table = 'test_posts';
    protected $fillable = ['id'];

    protected static function newFactory()
    {
        return new MorphOneInversePostModelFactory();
    }

    public function image(): MorphOne
    {
        return $this->morphOne(MorphOneInverseImageModel::class, 'imageable')->inverse('imageable');
    }

    public function guessedImage(): MorphOne
    {
        return $this->morphOne(MorphOneInverseImageModel::class, 'imageable')->inverse();
    }
}

class MorphOneInversePostModelFactory extends Factory
{
    protected $model = MorphOneInversePostModel::class;

    public function definition()
    {
        return [];
    }
}

class MorphOneInverseImageModel extends Model
{
    use HasFactory;

    protected $table = 'test_images';
    protected $fillable = ['id', 'imageable_type', 'imageable_id'];

    protected static function newFactory()
    {
        return new MorphOneInverseImageModelFactory();
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo('imageable');
    }
}

class MorphOneInverseImageModelFactory extends Factory
{
    protected $model = MorphOneInverseImageModel::class;

    public function definition()
    {
        return [
            'imageable_type' => MorphOneInversePostModel::class,
            'imageable_id' => MorphOneInversePostModel::factory(),
        ];
    }
}
