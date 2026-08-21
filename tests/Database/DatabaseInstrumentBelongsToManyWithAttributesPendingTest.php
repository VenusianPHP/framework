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
function dbBtmWithAttrPendingConnection($connection = 'default')
{
    return Model::getConnectionResolver()->connection($connection);
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmWithAttrPendingSchema($connection = 'default')
{
    return dbBtmWithAttrPendingConnection($connection)->getSchemaBuilder();
}

function dbBtmWithAttrPendingCreateSchema()
{
    dbBtmWithAttrPendingSchema()->create('pending_attributes_posts', function ($table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->timestamps();
    });

    dbBtmWithAttrPendingSchema()->create('pending_attributes_tags', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->boolean('visible')->nullable();
        $table->timestamps();
    });

    dbBtmWithAttrPendingSchema()->create('pending_attributes_pivot', function ($table) {
        $table->integer('post_id');
        $table->integer('tag_id');
        $table->string('type');
    });

    dbBtmWithAttrPendingSchema()->create('pending_attributes_taggables', function ($table) {
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
    dbBtmWithAttrPendingCreateSchema();
});

afterEach(function () {
    dbBtmWithAttrPendingSchema()->drop('pending_attributes_posts');
    dbBtmWithAttrPendingSchema()->drop('pending_attributes_tags');
    dbBtmWithAttrPendingSchema()->drop('pending_attributes_pivot');
});

test('creates pending attributes and pivot values', function () {
    $post = ManyToManyPendingAttributesPost::create();
    $tag = $post->metaTags()->create(['name' => 'long article']);

    expect($tag->name)->toBe('long article');
    expect($tag->visible)->toBeTrue();

    $pivot = DB::table('pending_attributes_pivot')->first();
    expect($pivot->type)->toBe('meta');
    expect($pivot->post_id)->toBe($post->id);
    expect($pivot->tag_id)->toBe($tag->id);
});

test('queries pending attributes and pivot values', function () {
    $post = new ManyToManyPendingAttributesPost(['id' => 2]);
    $wheres = $post->metaTags()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_pivot.tag_id',
        'operator' => '=',
        'value' => 2,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_pivot.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);

    // Ensure no other wheres exist
    expect($wheres)->toHaveCount(2);
});

test('morph to many pending attributes', function () {
    $post = new ManyToManyPendingAttributesPost(['id' => 2]);
    $wheres = $post->morphedTags()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.taggable_type',
        'operator' => '=',
        'value' => ManyToManyPendingAttributesPost::class,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.taggable_id',
        'operator' => '=',
        'value' => 2,
        'boolean' => 'and',
    ]);

    // Ensure no other wheres exist
    expect($wheres)->toHaveCount(3);

    $tag = $post->morphedTags()->create(['name' => 'new tag']);

    expect($tag->visible)->toBeTrue();
    expect($tag->name)->toBe('new tag');
    expect($post->morphedTags()->first()->id)->toBe($tag->id);
});

test('morphed by many pending attributes', function () {
    $tag = new ManyToManyPendingAttributesTag(['id' => 4]);
    $wheres = $tag->morphedPosts()->toBase()->wheres;

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.type',
        'operator' => '=',
        'value' => 'meta',
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.taggable_type',
        'operator' => '=',
        'value' => ManyToManyPendingAttributesPost::class,
        'boolean' => 'and',
    ]);

    expect($wheres)->toContain([
        'type' => 'Basic',
        'column' => 'pending_attributes_taggables.tag_id',
        'operator' => '=',
        'value' => 4,
        'boolean' => 'and',
    ]);

    // Ensure no other wheres exist
    expect($wheres)->toHaveCount(3);

    $post = $tag->morphedPosts()->create();
    expect($post->title)->toBe('Title!');
    expect($tag->morphedPosts()->first()->id)->toBe($post->id);
});

class ManyToManyPendingAttributesPost extends Model
{
    protected $guarded = [];
    protected $table = 'pending_attributes_posts';

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ManyToManyPendingAttributesTag::class,
            'pending_attributes_pivot',
            'tag_id',
            'post_id',
        );
    }

    public function metaTags(): BelongsToMany
    {
        return $this->tags()
            ->withAttributes('visible', true, asConditions: false)
            ->withPivotValue('type', 'meta');
    }

    public function morphedTags(): MorphToMany
    {
        return $this
            ->morphToMany(
                ManyToManyPendingAttributesTag::class,
                'taggable',
                'pending_attributes_taggables',
                relatedPivotKey: 'tag_id'
            )
            ->withAttributes('visible', true, asConditions: false)
            ->withPivotValue('type', 'meta');
    }
}

class ManyToManyPendingAttributesTag extends Model
{
    protected $guarded = [];
    protected $table = 'pending_attributes_tags';

    public function morphedPosts(): MorphToMany
    {
        return $this
            ->morphedByMany(
                ManyToManyPendingAttributesPost::class,
                'taggable',
                'pending_attributes_taggables',
                'tag_id',
            )
            ->withAttributes('title', 'Title!', asConditions: false)
            ->withPivotValue('type', 'meta');
    }
}
