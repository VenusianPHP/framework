<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function dbMorphOOMConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbMorphOOMSchema()
{
    return dbMorphOOMConnection()->getSchemaBuilder();
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbMorphOOMSchema()->create('products', function ($table) {
        $table->increments('id');
    });

    dbMorphOOMSchema()->create('states', function ($table) {
        $table->increments('id');
        $table->morphs('stateful');
        $table->string('state');
        $table->string('type')->nullable();
    });
});

afterEach(function () {
    dbMorphOOMSchema()->drop('products');
    dbMorphOOMSchema()->drop('states');
});

test('eager loading applies constraints to inner join sub query', function () {
    $product = MorphOneOfManyTestProduct::create();
    $relation = $product->current_state();
    $relation->addEagerConstraints([$product]);
    expect($relation->getOneOfManySubQuery()->toSql())->toBe('select MAX("states"."id") as "id_aggregate", "states"."stateful_id", "states"."stateful_type" from "states" where "states"."stateful_type" = ? and "states"."stateful_id" = ? and "states"."stateful_id" is not null and "states"."stateful_id" in (1) and "states"."stateful_type" = ? group by "states"."stateful_id", "states"."stateful_type"');
});

test('receiving model', function () {
    $product = MorphOneOfManyTestProduct::create();
    $product->states()->create([
        'state' => 'draft',
    ]);
    $product->states()->create([
        'state' => 'active',
    ]);

    expect($product->current_state)->not->toBeNull()
        ->and($product->current_state->state)->toBe('active');
});

test('morph type', function () {
    $product = MorphOneOfManyTestProduct::create();
    $product->states()->create([
        'state' => 'draft',
    ]);
    $product->states()->create([
        'state' => 'active',
    ]);
    $state = $product->states()->make([
        'state' => 'foo',
    ]);
    $state->stateful_type = 'bar';
    $state->save();

    expect($product->current_state)->not->toBeNull()
        ->and($product->current_state->state)->toBe('active');
});

test('force create morph type', function () {
    $product = MorphOneOfManyTestProduct::create();
    $state = $product->states()->forceCreate([
        'state' => 'active',
    ]);

    expect($state)->not->toBeNull()
        ->and($product->current_state->stateful_type)->toBe(MorphOneOfManyTestProduct::class);
});

test('exists', function () {
    $product = MorphOneOfManyTestProduct::create();
    $previousState = $product->states()->create([
        'state' => 'draft',
    ]);
    $currentState = $product->states()->create([
        'state' => 'active',
    ]);

    $exists = MorphOneOfManyTestProduct::whereHas('current_state', function ($q) use ($previousState) {
        $q->whereKey($previousState->getKey());
    })->exists();
    expect($exists)->toBeFalse();

    $exists = MorphOneOfManyTestProduct::whereHas('current_state', function ($q) use ($currentState) {
        $q->whereKey($currentState->getKey());
    })->exists();
    expect($exists)->toBeTrue();
});

test('with where has', function () {
    $product = MorphOneOfManyTestProduct::create();
    $previousState = $product->states()->create([
        'state' => 'draft',
    ]);
    $currentState = $product->states()->create([
        'state' => 'active',
    ]);

    $exists = MorphOneOfManyTestProduct::withWhereHas('current_state', function ($q) use ($previousState) {
        $q->whereKey($previousState->getKey());
    })->exists();
    expect($exists)->toBeFalse();

    $exists = MorphOneOfManyTestProduct::withWhereHas('current_state', function ($q) use ($currentState) {
        $q->whereKey($currentState->getKey());
    })->get();

    expect($exists)->toHaveCount(1)
        ->and($exists->first()->relationLoaded('current_state'))->toBeTrue()
        ->and($exists->first()->current_state->state)->toBe($currentState->state);
});

test('with where relation', function () {
    $product = MorphOneOfManyTestProduct::create();
    $currentState = $product->states()->create([
        'state' => 'active',
    ]);

    $exists = MorphOneOfManyTestProduct::withWhereRelation('current_state', 'state', 'active')->exists();
    expect($exists)->toBeTrue();

    $exists = MorphOneOfManyTestProduct::withWhereRelation('current_state', 'state', 'active')->get();

    expect($exists)->toHaveCount(1)
        ->and($exists->first()->relationLoaded('current_state'))->toBeTrue()
        ->and($exists->first()->current_state->state)->toBe($currentState->state);
});

test('with exists', function () {
    $product = MorphOneOfManyTestProduct::create();

    $product = MorphOneOfManyTestProduct::withExists('current_state')->first();
    expect($product->current_state_exists)->toBeFalse();

    $product->states()->create([
        'state' => 'draft',
    ]);
    $product = MorphOneOfManyTestProduct::withExists('current_state')->first();
    expect($product->current_state_exists)->toBeTrue();
});

test('with exists with constraints in join sub select', function () {
    $product = MorphOneOfManyTestProduct::create();

    $product = MorphOneOfManyTestProduct::withExists('current_foo_state')->first();
    expect($product->current_foo_state_exists)->toBeFalse();

    $product->states()->create([
        'state' => 'draft',
        'type' => 'foo',
    ]);
    $product = MorphOneOfManyTestProduct::withExists('current_foo_state')->first();
    expect($product->current_foo_state_exists)->toBeTrue();
});

/**
 * Instrument Models...
 */
class MorphOneOfManyTestProduct extends Instrument
{
    protected $table = 'products';
    protected $guarded = [];
    public $timestamps = false;

    public function states()
    {
        return $this->morphMany(MorphOneOfManyTestState::class, 'stateful');
    }

    public function current_state()
    {
        return $this->morphOne(MorphOneOfManyTestState::class, 'stateful')->ofMany();
    }

    public function current_foo_state()
    {
        return $this->morphOne(MorphOneOfManyTestState::class, 'stateful')->ofMany(
            ['id' => 'max'],
            function ($q) {
                $q->where('type', 'foo');
            }
        );
    }
}

class MorphOneOfManyTestState extends Instrument
{
    protected $table = 'states';
    protected $guarded = [];
    public $timestamps = false;
    protected $fillable = ['state', 'type'];
}
