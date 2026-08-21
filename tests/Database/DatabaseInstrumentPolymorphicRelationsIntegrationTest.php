<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\Relation;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentPolymorphicRelationsIntegrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Bootstrap Instrument.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootInstrument();
        $db->setAsGlobal();

        $this->createSchema();
    }

    protected function createSchema()
    {
        $this->schema('default')->create('posts', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema('default')->create('images', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema('default')->create('tags', function ($table) {
            $table->increments('id');
            $table->timestamps();
        });

        $this->schema('default')->create('taggables', function ($table) {
            $table->integer('instrument_many_to_many_polymorphic_test_tag_id');
            $table->integer('taggable_id');
            $table->string('taggable_type');
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (['default'] as $connection) {
            $this->schema($connection)->drop('posts');
            $this->schema($connection)->drop('images');
            $this->schema($connection)->drop('tags');
            $this->schema($connection)->drop('taggables');
        }

        Relation::morphMap([], false);

        parent::tearDown();
    }

    public function testCreation()
    {
        $post = InstrumentManyToManyPolymorphicTestPost::create();
        $image = InstrumentManyToManyPolymorphicTestImage::create();
        $tag = InstrumentManyToManyPolymorphicTestTag::create();
        $tag2 = InstrumentManyToManyPolymorphicTestTag::create();

        $post->tags()->attach($tag->id);
        $post->tags()->attach($tag2->id);
        $image->tags()->attach($tag->id);

        $this->assertCount(2, $post->tags);
        $this->assertCount(1, $image->tags);
        $this->assertCount(1, $tag->posts);
        $this->assertCount(1, $tag->images);
        $this->assertCount(1, $tag2->posts);
        $this->assertCount(0, $tag2->images);
    }

    public function testEagerLoading()
    {
        $post = InstrumentManyToManyPolymorphicTestPost::create();
        $tag = InstrumentManyToManyPolymorphicTestTag::create();
        $post->tags()->attach($tag->id);

        $post = InstrumentManyToManyPolymorphicTestPost::with('tags')->whereId(1)->first();
        $tag = InstrumentManyToManyPolymorphicTestTag::with('posts')->whereId(1)->first();

        $this->assertTrue($post->relationLoaded('tags'));
        $this->assertTrue($tag->relationLoaded('posts'));
        $this->assertEquals($tag->id, $post->tags->first()->id);
        $this->assertEquals($post->id, $tag->posts->first()->id);
    }

    public function testChunkById()
    {
        $post = InstrumentManyToManyPolymorphicTestPost::create();
        $tag1 = InstrumentManyToManyPolymorphicTestTag::create();
        $tag2 = InstrumentManyToManyPolymorphicTestTag::create();
        $tag3 = InstrumentManyToManyPolymorphicTestTag::create();
        $post->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

        $count = 0;
        $iterations = 0;
        $post->tags()->chunkById(2, function ($tags) use (&$iterations, &$count) {
            $this->assertInstanceOf(InstrumentManyToManyPolymorphicTestTag::class, $tags->first());
            $count += $tags->count();
            $iterations++;
        });

        $this->assertEquals(2, $iterations);
        $this->assertEquals(3, $count);
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return \Voyager\Database\Connection
     */
    protected function connection($connection = 'default')
    {
        return Instrument::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Voyager\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

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
