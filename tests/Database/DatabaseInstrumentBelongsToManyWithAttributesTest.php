<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsToMany;
use Voyager\Database\Instrument\Relations\MorphToMany;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function dbBtmWithAttrConnection($connection = 'default')
{
    return Model::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmWithAttrSchema($connection = 'default')
{
    return dbBtmWithAttrConnection($connection)->getSchemaBuilder();
}

function dbBtmWithAttrCreateSchema()
{
    dbBtmWithAttrSchema()->create('with_attributes_posts', function ($table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->timestamps();
    });

    dbBtmWithAttrSchema()->create('with_attributes_tags', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->boolean('visible')->nullable();
        $table->timestamps();
    });

    dbBtmWithAttrSchema()->create('with_attributes_pivot', function ($table) {
        $table->integer('post_id');
        $table->integer('tag_id');
        $table->string('type');
    });

    dbBtmWithAttrSchema()->create('with_attributes_taggables', function ($table) {
        $table->integer('tag_id');
        $table->integer('taggable_id');
        $table->string('taggable_type');
        $table->string('type');
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
    dbBtmWithAttrCreateSchema();
});

afterEach(function () {
    dbBtmWithAttrSchema()->drop('with_attributes_posts');
    dbBtmWithAttrSchema()->drop('with_attributes_tags');
    dbBtmWithAttrSchema()->drop('with_attributes_pivot');
});

test('creates with attributes and pivot values', function () {
    $post = ManyToManyWithAttributesPost::create();
    $tag = $post->metaTags()->create(['name' => 'long article']);

    expect($tag->name)->toBe('long article');
    expect($tag->visible)->toBeTrue();

    $pivot = DB::table('with_attributes_pivot')->first();
    expect($pivot->type)->toBe('meta');
    expect($pivot->post_id)->toBe($post->id);
    expect($pivot->tag_id)->toBe($tag->id);
});

test('queries with attributes and pivot values', function () {
    $post = new ManyToManyWithAttributesPost(['id' => 2]);
    $wheres = $post->metaTags()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_tags.visible',
        'operator' => '=',
        'value' => true,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_pivot.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);
});

test('morph to many with attributes', function () {
    $post = new ManyToManyWithAttributesPost(['id' => 2]);
    $wheres = $post->morphedTags()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_tags.visible',
        'operator' => '=',
        'value' => true,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.taggable_type',
        'operator' => '=',
        'value' => ManyToManyWithAttributesPost::class,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.taggable_id',
        'operator' => '=',
        'value' => 2,
        'boolean' => 'and',
    ]);

    $tag = $post->morphedTags()->create(['name' => 'new tag']);

    expect($tag->visible)->toBeTrue();
    expect($tag->name)->toBe('new tag');
    expect($post->morphedTags()->first()->id)->toBe($tag->id);
});

test('morphed by many with attributes', function () {
    $tag = new ManyToManyWithAttributesTag(['id' => 4]);
    $wheres = $tag->morphedPosts()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_posts.title',
        'operator' => '=',
        'value' => 'Title!',
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.taggable_type',
        'operator' => '=',
        'value' => ManyToManyWithAttributesPost::class,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'with_attributes_taggables.tag_id',
        'operator' => '=',
        'value' => 4,
        'boolean' => 'and',
    ]);

    $post = $tag->morphedPosts()->create();
    expect($post->title)->toBe('Title!');
    expect($tag->morphedPosts()->first()->id)->toBe($post->id);
});

class ManyToManyWithAttributesPost extends Model
{
    protected $guarded = [];
    protected $table = 'with_attributes_posts';

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ManyToManyWithAttributesTag::class,
            'with_attributes_pivot',
            'tag_id',
            'post_id',
        );
    }

    public function metaTags(): BelongsToMany
    {
        return $this->tags()
            ->withAttributes('visible', true)
            ->withPivotValue('type', 'meta');
    }

    public function morphedTags(): MorphToMany
    {
        return $this
            ->morphToMany(
                ManyToManyWithAttributesTag::class,
                'taggable',
                'with_attributes_taggables',
                relatedPivotKey: 'tag_id'
            )
            ->withAttributes('visible', true)
            ->withPivotValue('type', 'meta');
    }
}

class ManyToManyWithAttributesTag extends Model
{
    protected $guarded = [];
    protected $table = 'with_attributes_tags';

    public function morphedPosts(): MorphToMany
    {
        return $this
            ->morphedByMany(
                ManyToManyWithAttributesPost::class,
                'taggable',
                'with_attributes_taggables',
                'tag_id',
            )
            ->withAttributes('title', 'Title!')
            ->withPivotValue('type', 'meta');
    }
}
