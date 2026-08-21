<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\Relation;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function dbPolyRelIntegrationConnection($connection = 'default')
{
    return Instrument::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbPolyRelIntegrationSchema($connection = 'default')
{
    return dbPolyRelIntegrationConnection($connection)->getSchemaBuilder();
}

function dbPolyRelIntegrationCreateSchema()
{
    dbPolyRelIntegrationSchema('default')->create('posts', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    dbPolyRelIntegrationSchema('default')->create('images', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    dbPolyRelIntegrationSchema('default')->create('tags', function ($table) {
        $table->increments('id');
        $table->timestamps();
    });

    dbPolyRelIntegrationSchema('default')->create('taggables', function ($table) {
        $table->integer('instrument_many_to_many_polymorphic_test_tag_id');
        $table->integer('taggable_id');
        $table->string('taggable_type');
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

    dbPolyRelIntegrationCreateSchema();
});

afterEach(function () {
    foreach (['default'] as $connection) {
        dbPolyRelIntegrationSchema($connection)->drop('posts');
        dbPolyRelIntegrationSchema($connection)->drop('images');
        dbPolyRelIntegrationSchema($connection)->drop('tags');
        dbPolyRelIntegrationSchema($connection)->drop('taggables');
    }

    Relation::morphMap([], false);
});

test('creation', function () {
    $post = InstrumentManyToManyPolymorphicTestPost::create();
    $image = InstrumentManyToManyPolymorphicTestImage::create();
    $tag = InstrumentManyToManyPolymorphicTestTag::create();
    $tag2 = InstrumentManyToManyPolymorphicTestTag::create();

    $post->tags()->attach($tag->id);
    $post->tags()->attach($tag2->id);
    $image->tags()->attach($tag->id);

    expect($post->tags)->toHaveCount(2)
        ->and($image->tags)->toHaveCount(1)
        ->and($tag->posts)->toHaveCount(1)
        ->and($tag->images)->toHaveCount(1)
        ->and($tag2->posts)->toHaveCount(1)
        ->and($tag2->images)->toHaveCount(0);
});

test('eager loading', function () {
    $post = InstrumentManyToManyPolymorphicTestPost::create();
    $tag = InstrumentManyToManyPolymorphicTestTag::create();
    $post->tags()->attach($tag->id);

    $post = InstrumentManyToManyPolymorphicTestPost::with('tags')->whereId(1)->first();
    $tag = InstrumentManyToManyPolymorphicTestTag::with('posts')->whereId(1)->first();

    expect($post->relationLoaded('tags'))->toBeTrue()
        ->and($tag->relationLoaded('posts'))->toBeTrue()
        ->and($post->tags->first()->id)->toBe($tag->id)
        ->and($tag->posts->first()->id)->toBe($post->id);
});

test('chunk by id', function () {
    $post = InstrumentManyToManyPolymorphicTestPost::create();
    $tag1 = InstrumentManyToManyPolymorphicTestTag::create();
    $tag2 = InstrumentManyToManyPolymorphicTestTag::create();
    $tag3 = InstrumentManyToManyPolymorphicTestTag::create();
    $post->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

    $count = 0;
    $iterations = 0;
    $post->tags()->chunkById(2, function ($tags) use (&$iterations, &$count) {
        expect($tags->first())->toBeInstanceOf(InstrumentManyToManyPolymorphicTestTag::class);
        $count += $tags->count();
        $iterations++;
    });

    expect($iterations)->toBe(2)
        ->and($count)->toBe(3);
});

/**
 * Instrument Models...
 */
class InstrumentManyToManyPolymorphicTestPost extends Instrument
{
    protected $table = 'posts';
    protected $guarded = [];

    public function tags()
    {
        return $this->morphToMany(InstrumentManyToManyPolymorphicTestTag::class, 'taggable');
    }
}

class InstrumentManyToManyPolymorphicTestImage extends Instrument
{
    protected $table = 'images';
    protected $guarded = [];

    public function tags()
    {
        return $this->morphToMany(InstrumentManyToManyPolymorphicTestTag::class, 'taggable');
    }
}

class InstrumentManyToManyPolymorphicTestTag extends Instrument
{
    protected $table = 'tags';
    protected $guarded = [];

    public function posts()
    {
        return $this->morphedByMany(InstrumentManyToManyPolymorphicTestPost::class, 'taggable');
    }

    public function images()
    {
        return $this->morphedByMany(InstrumentManyToManyPolymorphicTestImage::class, 'taggable');
    }
}
