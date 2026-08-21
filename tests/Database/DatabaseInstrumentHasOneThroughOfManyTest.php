<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Connection;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\HasMany;
use Voyager\Database\Instrument\Relations\HasManyThrough;
use Voyager\Database\Instrument\Relations\HasOneThrough;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\Database\Schema\Builder;
use InvalidArgumentException;

function dbHasOneThroughOfManyConnection(): Connection
{
    return Instrument::getConnectionResolver()->connection();
}

function dbHasOneThroughOfManySchema(): Builder
{
    return dbHasOneThroughOfManyConnection()->getSchemaBuilder();
}

function dbHasOneThroughOfManyCreateSchema(): void
{
    dbHasOneThroughOfManySchema()->create('users', function ($table) {
        $table->increments('id');
    });

    dbHasOneThroughOfManySchema()->create('intermediates', function ($table) {
        $table->increments('id');
        $table->foreignId('user_id');
    });

    dbHasOneThroughOfManySchema()->create('logins', function ($table) {
        $table->increments('id');
        $table->foreignId('intermediate_id');
        $table->dateTime('deleted_at')->nullable();
    });

    dbHasOneThroughOfManySchema()->create('states', function ($table) {
        $table->increments('id');
        $table->string('state');
        $table->string('type');
        $table->foreignId('intermediate_id');
        $table->timestamps();
    });

    dbHasOneThroughOfManySchema()->create('prices', function ($table) {
        $table->increments('id');
        $table->dateTime('published_at');
        $table->foreignId('intermediate_id');
    });
}

beforeEach(function () {
    $db = new DB;
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $db->bootInstrument();
    $db->setAsGlobal();

    dbHasOneThroughOfManyCreateSchema();
});

afterEach(function () {
    dbHasOneThroughOfManySchema()->drop('users');
    dbHasOneThroughOfManySchema()->drop('intermediates');
    dbHasOneThroughOfManySchema()->drop('logins');
    dbHasOneThroughOfManySchema()->drop('states');
    dbHasOneThroughOfManySchema()->drop('prices');
});

test('it guesses relation name', function () {
    $user = HasOneThroughOfManyTestUser::make();
    expect($user->latest_login()->getRelationName())->toBe('latest_login');
});

test('it guesses relation name and adds of many when table name is relation name', function () {
    $model = HasOneThroughOfManyTestModel::make();
    expect($model->logins()->getRelationName())->toBe('logins_of_many');
});

test('relation name can be set', function () {
    $user = HasOneThroughOfManyTestUser::create();

    $relation = $user->latest_login()->ofMany('id', 'max', 'foo');
    expect($relation->getRelationName())->toBe('foo');

    $relation = $user->latest_login()->latestOfMany('id', 'bar');
    expect($relation->getRelationName())->toBe('bar');

    $relation = $user->latest_login()->oldestOfMany('id', 'baz');
    expect($relation->getRelationName())->toBe('baz');
});

test('correct latest of many query', function () {
    $user = HasOneThroughOfManyTestUser::create();
    $relation = $user->latest_login();
    expect($relation->getQuery()->toSql())->toBe('select "logins".* from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" inner join (select MAX("logins"."id") as "id_aggregate", "intermediates"."user_id" from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" where "intermediates"."user_id" = ? group by "intermediates"."user_id") as "latest_login" on "latest_login"."id_aggregate" = "logins"."id" and "latest_login"."user_id" = "intermediates"."user_id" where "intermediates"."user_id" = ?');
});

test('eager loading applies constraints to inner join sub query', function () {
    $user = HasOneThroughOfManyTestUser::create();
    $relation = $user->latest_login();
    $relation->addEagerConstraints([$user]);
    expect($relation->getOneOfManySubQuery()->toSql())->toBe('select MAX("logins"."id") as "id_aggregate", "intermediates"."user_id" from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" where "intermediates"."user_id" = ? and "intermediates"."user_id" in (1) group by "intermediates"."user_id"');
});

test('eager loading applies constraints to query', function () {
    $user = HasOneThroughOfManyTestUser::create();
    $relation = $user->latest_login();
    $relation->addEagerConstraints([$user]);
    expect($relation->getQuery()->toSql())->toBe('select "logins".* from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" inner join (select MAX("logins"."id") as "id_aggregate", "intermediates"."user_id" from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" where "intermediates"."user_id" = ? and "intermediates"."user_id" in (1) group by "intermediates"."user_id") as "latest_login" on "latest_login"."id_aggregate" = "logins"."id" and "latest_login"."user_id" = "intermediates"."user_id" where "intermediates"."user_id" = ?');
});

test('global scope is not applied when relation is defined without global scope', function () {
    HasOneThroughOfManyTestLogin::addGlobalScope('test', function ($query) {
        $query->orderBy($query->qualifyColumn('id'));
    });

    $user = HasOneThroughOfManyTestUser::create();
    $relation = $user->latest_login_without_global_scope();
    $relation->addEagerConstraints([$user]);
    expect($relation->getQuery()->toSql())->toBe('select "logins".* from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" inner join (select MAX("logins"."id") as "id_aggregate", "intermediates"."user_id" from "logins" inner join "intermediates" on "intermediates"."id" = "logins"."intermediate_id" where "intermediates"."user_id" = ? and "intermediates"."user_id" in (1) group by "intermediates"."user_id") as "latestOfMany" on "latestOfMany"."id_aggregate" = "logins"."id" and "latestOfMany"."user_id" = "intermediates"."user_id" where "intermediates"."user_id" = ?');

    HasOneThroughOfManyTestLogin::addGlobalScope('test', function ($query) {
    });
});

test('global scope is not applied when relation is defined without global scope with complex query', function () {
    HasOneThroughOfManyTestPrice::addGlobalScope('test', function ($query) {
        $query->orderBy($query->qualifyColumn('id'));
    });

    $user = HasOneThroughOfManyTestUser::create();
    $relation = $user->price_without_global_scope();
    expect($relation->getQuery()->toSql())->toBe('select "prices".* from "prices" inner join "intermediates" on "intermediates"."id" = "prices"."intermediate_id" inner join (select max("prices"."id") as "id_aggregate", min("prices"."published_at") as "published_at_aggregate", "intermediates"."user_id" from "prices" inner join "intermediates" on "intermediates"."id" = "prices"."intermediate_id" inner join (select max("prices"."published_at") as "published_at_aggregate", "intermediates"."user_id" from "prices" inner join "intermediates" on "intermediates"."id" = "prices"."intermediate_id" where "published_at" < ? and "intermediates"."user_id" = ? group by "intermediates"."user_id") as "price_without_global_scope" on "price_without_global_scope"."published_at_aggregate" = "prices"."published_at" and "price_without_global_scope"."user_id" = "intermediates"."user_id" where "published_at" < ? group by "intermediates"."user_id") as "price_without_global_scope" on "price_without_global_scope"."id_aggregate" = "prices"."id" and "price_without_global_scope"."published_at_aggregate" = "prices"."published_at" and "price_without_global_scope"."user_id" = "intermediates"."user_id" where "intermediates"."user_id" = ?');

    HasOneThroughOfManyTestPrice::addGlobalScope('test', function ($query) {
    });
});

test('qualifying sub select column', function () {
    $user = HasOneThroughOfManyTestUser::make();
    expect($user->latest_login()->qualifySubSelectColumn('id'))->toBe('latest_login.id');
});

test('it fails when using invalid aggregate', function () {
    $user = HasOneThroughOfManyTestUser::make();
    $user->latest_login_with_invalid_aggregate();
})->throws(InvalidArgumentException::class, 'Invalid aggregate [count] used within ofMany relation. Available aggregates: MIN, MAX');

test('it gets correct results', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->last()->logins()->create();
    $latestLogin = $user->intermediates->first()->logins()->create();

    $result = $user->latest_login()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($latestLogin->id);
});

test('result does not have aggregate column', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(1)->create();
    $user->intermediates->first()->logins()->create();

    $result = $user->latest_login()->getResults();
    expect($result)->not->toBeNull();
    expect(isset($result->id_aggregate))->toBeFalse();
});

test('it gets correct results using shortcut method', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->last()->logins()->create();
    $latestLogin = $user->intermediates->first()->logins()->create();

    $result = $user->latest_login_with_shortcut()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($latestLogin->id);
});

test('it gets correct results using shortcut receiving multiple columns method', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->intermediates->first()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $result = $user->price_with_shortcut()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($price->id);
});

test('key is added to aggregates when missing', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->intermediates->first()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $result = $user->price_without_key_in_aggregates()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($price->id);
});

test('it gets with constraints correct results', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->last()->logins()->create();
    $user->intermediates->first()->logins()->create();

    $result = $user->latest_login()->whereKey($previousLogin->getKey())->getResults();
    expect($result)->toBeNull();
});

test('it eager loads correct models', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->logins()->create();
    $latestLogin = $user->intermediates->first()->logins()->create();

    $user = HasOneThroughOfManyTestUser::with('latest_login')->first();

    expect($user->relationLoaded('latest_login'))->toBeTrue();
    expect($user->latest_login->id)->toBe($latestLogin->id);
});

test('it joins other table in sub query', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->first()->logins()->create();

    expect($user->latest_login_with_foo_state)->toBeNull();

    $user->unsetRelation('latest_login_with_foo_state');
    $user->intermediates->first()->states()->create([
        'type' => 'foo',
        'state' => 'draft',
    ]);

    expect($user->latest_login_with_foo_state)->not->toBeNull();
});

test('has nested', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->first()->logins()->create();
    $latestLogin = $user->intermediates->last()->logins()->create();

    $found = HasOneThroughOfManyTestUser::whereHas('latest_login', function ($query) use ($latestLogin) {
        $query->where('logins.id', $latestLogin->id);
    })->exists();
    expect($found)->toBeTrue();

    $found = HasOneThroughOfManyTestUser::whereHas('latest_login', function ($query) use ($previousLogin) {
        $query->where('logins.id', $previousLogin->id);
    })->exists();
    expect($found)->toBeFalse();
});

test('with has nested', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->first()->logins()->create();
    $latestLogin = $user->intermediates->last()->logins()->create();

    $found = HasOneThroughOfManyTestUser::withWhereHas('latest_login', function ($query) use ($latestLogin) {
        $query->where('logins.id', $latestLogin->id);
    })->first();

    expect((bool) $found)->toBeTrue();
    expect($found->relationLoaded('latest_login'))->toBeTrue();
    $this->assertEquals($found->latest_login->id, $latestLogin->id);

    $found = HasOneThroughOfManyTestUser::withWhereHas('latest_login', function ($query) use ($previousLogin) {
        $query->where('logins.id', $previousLogin->id);
    })->exists();

    expect($found)->toBeFalse();
});

test('has count', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->logins()->create();
    $user->intermediates->first()->logins()->create();

    $user = HasOneThroughOfManyTestUser::withCount('latest_login')->first();
    $this->assertEquals(1, $user->latest_login_count);
});

test('exists', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->last()->logins()->create();
    $latestLogin = $user->intermediates->first()->logins()->create();

    expect($user->latest_login()->whereKey($previousLogin->getKey())->exists())->toBeFalse();
    expect($user->latest_login()->whereKey($latestLogin->getKey())->exists())->toBeTrue();
});

test('is method', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $login1 = $user->intermediates->last()->logins()->create();
    $login2 = $user->intermediates->first()->logins()->create();

    expect($user->latest_login()->is($login1))->toBeFalse();
    expect($user->latest_login()->is($login2))->toBeTrue();
});

test('is not method', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $login1 = $user->intermediates->last()->logins()->create();
    $login2 = $user->intermediates->first()->logins()->create();

    expect($user->latest_login()->isNot($login1))->toBeTrue();
    expect($user->latest_login()->isNot($login2))->toBeFalse();
});

test('get', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $previousLogin = $user->intermediates->last()->logins()->create();
    $latestLogin = $user->intermediates->first()->logins()->create();

    $latestLogins = $user->latest_login()->get();
    expect($latestLogins)->toHaveCount(1);
    expect($latestLogins->first()->id)->toBe($latestLogin->id);

    $latestLogins = $user->latest_login()->whereKey($previousLogin->getKey())->get();
    expect($latestLogins)->toHaveCount(0);
});

test('count', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->logins()->create();
    $user->intermediates->first()->logins()->create();

    expect($user->latest_login()->count())->toBe(1);
});

test('aggregate', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $firstLogin = $user->intermediates->first()->logins()->create();
    $user->intermediates->last()->logins()->create();

    $user = HasOneThroughOfManyTestUser::first();
    expect($user->first_login->id)->toBe($firstLogin->id);
});

test('join constraints', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->states()->create([
        'type' => 'foo',
        'state' => 'draft',
    ]);
    $currentForState = $user->intermediates->first()->states()->create([
        'type' => 'foo',
        'state' => 'active',
    ]);
    $user->intermediates->first()->states()->create([
        'type' => 'bar',
        'state' => 'baz',
    ]);

    $user = HasOneThroughOfManyTestUser::first();
    expect($user->foo_state->id)->toBe($currentForState->id);
});

test('multiple aggregates', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user->intermediates->last()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->intermediates->first()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $user = HasOneThroughOfManyTestUser::first();
    expect($user->price->id)->toBe($price->id);
});

test('eager loading with multiple aggregates', function () {
    $user1 = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();
    $user2 = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();

    $user1->intermediates->last()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user1Price = $user1->intermediates->first()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user1->intermediates->first()->prices()->create([
        'published_at' => '2021-04-01 00:00:00',
    ]);

    $user2Price = $user2->intermediates->last()->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user2->intermediates->first()->prices()->create([
        'published_at' => '2021-04-01 00:00:00',
    ]);

    $users = HasOneThroughOfManyTestUser::with('price')->get();

    expect($users[0]->price)->not->toBeNull();
    expect($users[0]->price->id)->toBe($user1Price->id);

    expect($users[1]->price)->not->toBeNull();
    expect($users[1]->price->id)->toBe($user2Price->id);
});

test('with exists', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(1)->create();

    $user = HasOneThroughOfManyTestUser::withExists('latest_login')->first();
    expect($user->latest_login_exists)->toBeFalse();

    $user->intermediates->first()->logins()->create();
    $user = HasOneThroughOfManyTestUser::withExists('latest_login')->first();
    expect($user->latest_login_exists)->toBeTrue();
});

test('with exists with constraints in join sub select', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(1)->create();
    $user = HasOneThroughOfManyTestUser::withExists('foo_state')->first();

    expect($user->foo_state_exists)->toBeFalse();

    $user->intermediates->first()->states()->create([
        'type' => 'foo',
        'state' => 'bar',
    ]);
    $user = HasOneThroughOfManyTestUser::withExists('foo_state')->first();
    expect($user->foo_state_exists)->toBeTrue();
});

test('with soft deletes', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(1)->create();
    $user->intermediates->first()->logins()->create();
    $user->latest_login_with_soft_deletes;
    expect($user->latest_login_with_soft_deletes)->not->toBeNull();
});

test('with constraint not in aggregate', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();

    $previousFoo = $user->intermediates->last()->states()->create([
        'type' => 'foo',
        'state' => 'bar',
        'updated_at' => '2020-01-01 00:00:00',
    ]);
    $newFoo = $user->intermediates->first()->states()->create([
        'type' => 'foo',
        'state' => 'active',
        'updated_at' => '2021-01-01 12:00:00',
    ]);
    $newBar = $user->intermediates->first()->states()->create([
        'type' => 'bar',
        'state' => 'active',
        'updated_at' => '2021-01-01 12:00:00',
    ]);

    expect($user->last_updated_foo_state->id)->toBe($newFoo->id);
});

test('it gets correct result using at least two aggregates distinct from id', function () {
    $user = HasOneThroughOfManyTestUser::factory()->hasIntermediates(2)->create();

    $expectedState = $user->intermediates->last()->states()->create([
        'state' => 'state',
        'type' => 'type',
        'created_at' => '2023-01-01',
        'updated_at' => '2023-01-03',
    ]);

    $user->intermediates->first()->states()->create([
        'state' => 'state',
        'type' => 'type',
        'created_at' => '2023-01-01',
        'updated_at' => '2023-01-02',
    ]);

    expect($user->latest_updated_latest_created_state->id)->toBe($expectedState->id);
});

class HasOneThroughOfManyTestUser extends Instrument
{
    use HasFactory;
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
    protected static string $factory = HasOneThroughOfManyTestUserFactory::class;

    public function intermediates(): HasMany
    {
        return $this->hasMany(HasOneThroughOfManyTestIntermediate::class, 'user_id');
    }

    public function logins(): HasManyThrough
    {
        return $this->through('intermediates')->has('logins');
    }

    public function latest_login(): HasOneThrough
    {
        return $this->hasOneThrough(
            HasOneThroughOfManyTestLogin::class,
            HasOneThroughOfManyTestIntermediate::class,
            'user_id',
            'intermediate_id'
        )->ofMany();
    }

    public function latest_login_with_soft_deletes(): HasOneThrough
    {
        return $this->hasOneThrough(
            HasOneThroughOfManyTestLoginWithSoftDeletes::class,
            HasOneThroughOfManyTestIntermediate::class,
            'user_id',
            'intermediate_id',
        )->ofMany();
    }

    public function latest_login_with_shortcut(): HasOneThrough
    {
        return $this->logins()->one()->latestOfMany();
    }

    public function latest_login_with_invalid_aggregate(): HasOneThrough
    {
        return $this->logins()->one()->ofMany('id', 'count');
    }

    public function latest_login_without_global_scope(): HasOneThrough
    {
        return $this->logins()->one()->withoutGlobalScopes()->latestOfMany();
    }

    public function first_login(): HasOneThrough
    {
        return $this->logins()->one()->ofMany('id', 'min');
    }

    public function latest_login_with_foo_state(): HasOneThrough
    {
        return $this->logins()->one()->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->join('states', 'states.intermediate_id', 'logins.intermediate_id')
                    ->where('states.type', 'foo');
            }
        );
    }

    public function states(): HasManyThrough
    {
        return $this->through($this->intermediates())
            ->has(fn ($intermediate) => $intermediate->states());
    }

    public function foo_state(): HasOneThrough
    {
        return $this->states()->one()->ofMany(
            ['id' => 'max'],
            function ($q) {
                $q->where('type', 'foo');
            }
        );
    }

    public function last_updated_foo_state(): HasOneThrough
    {
        return $this->states()->one()->ofMany([
            'updated_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('type', 'foo');
        });
    }

    public function prices(): HasManyThrough
    {
        return $this->throughIntermediates()->hasPrices();
    }

    public function price(): HasOneThrough
    {
        return $this->prices()->one()->ofMany([
            'published_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('published_at', '<', now());
        });
    }

    public function price_without_key_in_aggregates(): HasOneThrough
    {
        return $this->prices()->one()->ofMany(['published_at' => 'MAX']);
    }

    public function price_with_shortcut(): HasOneThrough
    {
        return $this->prices()->one()->latestOfMany(['published_at', 'id']);
    }

    public function price_without_global_scope(): HasOneThrough
    {
        return $this->prices()->one()->withoutGlobalScopes()->ofMany([
            'published_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('published_at', '<', now());
        });
    }

    public function latest_updated_latest_created_state(): HasOneThrough
    {
        return $this->states()->one()->ofMany([
            'updated_at' => 'max',
            'created_at' => 'max',
        ]);
    }
}

class HasOneThroughOfManyTestIntermediate extends Instrument
{
    use HasFactory;
    protected $table = 'intermediates';
    protected $guarded = [];
    public $timestamps = false;
    protected static string $factory = HasOneThroughOfManyTestIntermediateFactory::class;

    public function logins(): HasMany
    {
        return $this->hasMany(HasOneThroughOfManyTestLogin::class, 'intermediate_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(HasOneThroughOfManyTestState::class, 'intermediate_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(HasOneThroughOfManyTestPrice::class, 'intermediate_id');
    }
}

class HasOneThroughOfManyTestModel extends Instrument
{
    public function logins(): HasOneThrough
    {
        return $this->hasOneThrough(
            HasOneThroughOfManyTestLogin::class,
            HasOneThroughOfManyTestIntermediate::class,
            'user_id',
            'intermediate_id',
        )->ofMany();
    }
}

class HasOneThroughOfManyTestLogin extends Instrument
{
    protected $table = 'logins';
    protected $guarded = [];
    public $timestamps = false;
}

class HasOneThroughOfManyTestLoginWithSoftDeletes extends Instrument
{
    use SoftDeletes;

    protected $table = 'logins';
    protected $guarded = [];
    public $timestamps = false;
}

class HasOneThroughOfManyTestState extends Instrument
{
    protected $table = 'states';
    protected $guarded = [];
    public $timestamps = true;
    protected $fillable = ['type', 'state', 'updated_at'];
}

class HasOneThroughOfManyTestPrice extends Instrument
{
    protected $table = 'prices';
    protected $guarded = [];
    public $timestamps = false;
    protected $fillable = ['published_at'];
    protected $casts = ['published_at' => 'datetime'];
}

class HasOneThroughOfManyTestUserFactory extends Factory
{
    protected $model = HasOneThroughOfManyTestUser::class;

    public function definition(): array
    {
        return [];
    }
}

class HasOneThroughOfManyTestIntermediateFactory extends Factory
{
    protected $model = HasOneThroughOfManyTestIntermediate::class;

    public function definition(): array
    {
        return ['user_id' => HasOneThroughOfManyTestUser::factory()];
    }
}
