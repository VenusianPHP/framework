<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\SoftDeletes;
use InvalidArgumentException;

function dbHasOneOfManyConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

function dbHasOneOfManySchema()
{
    return dbHasOneOfManyConnection()->getSchemaBuilder();
}

function dbHasOneOfManyCreateSchema()
{
    dbHasOneOfManySchema()->create('users', function ($table) {
        $table->increments('id');
    });

    dbHasOneOfManySchema()->create('logins', function ($table) {
        $table->increments('id');
        $table->foreignId('user_id');
        $table->dateTime('deleted_at')->nullable();
    });

    dbHasOneOfManySchema()->create('states', function ($table) {
        $table->increments('id');
        $table->string('state');
        $table->string('type');
        $table->foreignId('user_id');
        $table->timestamps();
    });

    dbHasOneOfManySchema()->create('prices', function ($table) {
        $table->increments('id');
        $table->dateTime('published_at');
        $table->foreignId('user_id');
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

    dbHasOneOfManyCreateSchema();
});

afterEach(function () {
    dbHasOneOfManySchema()->drop('users');
    dbHasOneOfManySchema()->drop('logins');
    dbHasOneOfManySchema()->drop('states');
    dbHasOneOfManySchema()->drop('prices');
});

test('it guesses relation name', function () {
    $user = HasOneOfManyTestUser::make();
    expect($user->latest_login()->getRelationName())->toBe('latest_login');
});

test('it guesses relation name and adds of many when table name is relation name', function () {
    $model = HasOneOfManyTestModel::make();
    expect($model->logins()->getRelationName())->toBe('logins_of_many');
});

test('relation name can be set', function () {
    $user = HasOneOfManyTestUser::create();

    // Using "ofMany"
    $relation = $user->latest_login()->ofMany('id', 'max', 'foo');
    expect($relation->getRelationName())->toBe('foo');

    // Using "latestOfMAny"
    $relation = $user->latest_login()->latestOfMAny('id', 'bar');
    expect($relation->getRelationName())->toBe('bar');

    // Using "oldestOfMAny"
    $relation = $user->latest_login()->oldestOfMAny('id', 'baz');
    expect($relation->getRelationName())->toBe('baz');
});

test('correct latest of many query', function () {
    $user = HasOneOfManyTestUser::create();
    $relation = $user->latest_login();
    expect($relation->getQuery()->toSql())->toBe('select "logins".* from "logins" inner join (select MAX("logins"."id") as "id_aggregate", "logins"."user_id" from "logins" where "logins"."user_id" = ? and "logins"."user_id" is not null group by "logins"."user_id") as "latest_login" on "latest_login"."id_aggregate" = "logins"."id" and "latest_login"."user_id" = "logins"."user_id" where "logins"."user_id" = ? and "logins"."user_id" is not null');
});

test('eager loading applies constraints to inner join sub query', function () {
    $user = HasOneOfManyTestUser::create();
    $relation = $user->latest_login();
    $relation->addEagerConstraints([$user]);
    expect($relation->getOneOfManySubQuery()->toSql())->toBe('select MAX("logins"."id") as "id_aggregate", "logins"."user_id" from "logins" where "logins"."user_id" = ? and "logins"."user_id" is not null and "logins"."user_id" in (1) group by "logins"."user_id"');
});

test('global scope is not applied when relation is defined without global scope', function () {
    HasOneOfManyTestLogin::addGlobalScope('test', function ($query) {
        $query->orderBy('id');
    });

    $user = HasOneOfManyTestUser::create();
    $relation = $user->latest_login_without_global_scope();
    $relation->addEagerConstraints([$user]);
    expect($relation->getQuery()->toSql())->toBe('select "logins".* from "logins" inner join (select MAX("logins"."id") as "id_aggregate", "logins"."user_id" from "logins" where "logins"."user_id" = ? and "logins"."user_id" is not null and "logins"."user_id" in (1) group by "logins"."user_id") as "latestOfMany" on "latestOfMany"."id_aggregate" = "logins"."id" and "latestOfMany"."user_id" = "logins"."user_id" where "logins"."user_id" = ? and "logins"."user_id" is not null');

    HasOneOfManyTestLogin::addGlobalScope('test', function ($query) {
    });
});

test('global scope is not applied when relation is defined without global scope with complex query', function () {
    HasOneOfManyTestPrice::addGlobalScope('test', function ($query) {
        $query->orderBy('id');
    });

    $user = HasOneOfManyTestUser::create();
    $relation = $user->price_without_global_scope();
    expect($relation->getQuery()->toSql())->toBe('select "prices".* from "prices" inner join (select max("prices"."id") as "id_aggregate", min("prices"."published_at") as "published_at_aggregate", "prices"."user_id" from "prices" inner join (select max("prices"."published_at") as "published_at_aggregate", "prices"."user_id" from "prices" where "published_at" < ? and "prices"."user_id" = ? and "prices"."user_id" is not null group by "prices"."user_id") as "price_without_global_scope" on "price_without_global_scope"."published_at_aggregate" = "prices"."published_at" and "price_without_global_scope"."user_id" = "prices"."user_id" where "published_at" < ? group by "prices"."user_id") as "price_without_global_scope" on "price_without_global_scope"."id_aggregate" = "prices"."id" and "price_without_global_scope"."published_at_aggregate" = "prices"."published_at" and "price_without_global_scope"."user_id" = "prices"."user_id" where "prices"."user_id" = ? and "prices"."user_id" is not null');

    HasOneOfManyTestPrice::addGlobalScope('test', function ($query) {
    });
});

test('qualifying sub select column', function () {
    $user = HasOneOfManyTestUser::create();
    expect($user->latest_login()->qualifySubSelectColumn('id'))->toBe('latest_login.id');
});

test('it fails when using invalid aggregate', function () {
    $user = HasOneOfManyTestUser::make();
    $user->latest_login_with_invalid_aggregate();
})->throws(InvalidArgumentException::class, 'Invalid aggregate [count] used within ofMany relation. Available aggregates: MIN, MAX');

test('it gets correct results', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $result = $user->latest_login()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($latestLogin->id);
});

test('result does not have aggregate column', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();

    $result = $user->latest_login()->getResults();
    expect($result)->not->toBeNull();
    expect(isset($result->id_aggregate))->toBeFalse();
});

test('it gets correct results using shortcut method', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $result = $user->latest_login_with_shortcut()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($latestLogin->id);
});

test('it gets correct results using shortcut receiving multiple columns method', function () {
    $user = HasOneOfManyTestUser::create();
    $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $result = $user->price_with_shortcut()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($price->id);
});

test('key is added to aggregates when missing', function () {
    $user = HasOneOfManyTestUser::create();
    $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $result = $user->price_without_key_in_aggregates()->getResults();
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($price->id);
});

test('it gets with constraints correct results', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $user->logins()->create();

    $result = $user->latest_login()->whereKey($previousLogin->getKey())->getResults();
    expect($result)->toBeNull();
});

test('it eager loads correct models', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $user = HasOneOfManyTestUser::with('latest_login')->first();

    expect($user->relationLoaded('latest_login'))->toBeTrue();
    expect($user->latest_login->id)->toBe($latestLogin->id);
});

test('it joins other table in sub query', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();

    expect($user->latest_login_with_foo_state)->toBeNull();

    $user->unsetRelation('latest_login_with_foo_state');
    $user->states()->create([
        'type' => 'foo',
        'state' => 'draft',
    ]);

    expect($user->latest_login_with_foo_state)->not->toBeNull();
});

test('has nested', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $found = HasOneOfManyTestUser::whereHas('latest_login', function ($query) use ($latestLogin) {
        $query->where('logins.id', $latestLogin->id);
    })->exists();
    expect($found)->toBeTrue();

    $found = HasOneOfManyTestUser::whereHas('latest_login', function ($query) use ($previousLogin) {
        $query->where('logins.id', $previousLogin->id);
    })->exists();
    expect($found)->toBeFalse();
});

test('with has nested', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $found = HasOneOfManyTestUser::withWhereHas('latest_login', function ($query) use ($latestLogin) {
        $query->where('logins.id', $latestLogin->id);
    })->first();

    expect((bool) $found)->toBeTrue();
    expect($found->relationLoaded('latest_login'))->toBeTrue();
    $this->assertEquals($found->latest_login->id, $latestLogin->id);

    $found = HasOneOfManyTestUser::withWhereHas('latest_login', function ($query) use ($previousLogin) {
        $query->where('logins.id', $previousLogin->id);
    })->exists();

    expect($found)->toBeFalse();
});

test('has count', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();
    $user->logins()->create();

    $user = HasOneOfManyTestUser::withCount('latest_login')->first();
    $this->assertEquals(1, $user->latest_login_count);
});

test('exists', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    expect($user->latest_login()->whereKey($previousLogin->getKey())->exists())->toBeFalse();
    expect($user->latest_login()->whereKey($latestLogin->getKey())->exists())->toBeTrue();
});

test('is method', function () {
    $user = HasOneOfManyTestUser::create();
    $login1 = $user->latest_login()->create();
    $login2 = $user->latest_login()->create();

    expect($user->latest_login()->is($login1))->toBeFalse();
    expect($user->latest_login()->is($login2))->toBeTrue();
});

test('is not method', function () {
    $user = HasOneOfManyTestUser::create();
    $login1 = $user->latest_login()->create();
    $login2 = $user->latest_login()->create();

    expect($user->latest_login()->isNot($login1))->toBeTrue();
    expect($user->latest_login()->isNot($login2))->toBeFalse();
});

test('get', function () {
    $user = HasOneOfManyTestUser::create();
    $previousLogin = $user->logins()->create();
    $latestLogin = $user->logins()->create();

    $latestLogins = $user->latest_login()->get();
    expect($latestLogins)->toHaveCount(1);
    expect($latestLogins->first()->id)->toBe($latestLogin->id);

    $latestLogins = $user->latest_login()->whereKey($previousLogin->getKey())->get();
    expect($latestLogins)->toHaveCount(0);
});

test('count', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();
    $user->logins()->create();

    expect($user->latest_login()->count())->toBe(1);
});

test('aggregate', function () {
    $user = HasOneOfManyTestUser::create();
    $firstLogin = $user->logins()->create();
    $user->logins()->create();

    $user = HasOneOfManyTestUser::first();
    expect($user->first_login->id)->toBe($firstLogin->id);
});

test('join constraints', function () {
    $user = HasOneOfManyTestUser::create();
    $user->states()->create([
        'type' => 'foo',
        'state' => 'draft',
    ]);
    $currentForState = $user->states()->create([
        'type' => 'foo',
        'state' => 'active',
    ]);
    $user->states()->create([
        'type' => 'bar',
        'state' => 'baz',
    ]);

    $user = HasOneOfManyTestUser::first();
    expect($user->foo_state->id)->toBe($currentForState->id);
});

test('multiple aggregates', function () {
    $user = HasOneOfManyTestUser::create();

    $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $price = $user->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);

    $user = HasOneOfManyTestUser::first();
    expect($user->price->id)->toBe($price->id);
});

test('eager loading with multiple aggregates', function () {
    $user1 = HasOneOfManyTestUser::create();
    $user2 = HasOneOfManyTestUser::create();

    $user1->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user1Price = $user1->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user1->prices()->create([
        'published_at' => '2021-04-01 00:00:00',
    ]);

    $user2Price = $user2->prices()->create([
        'published_at' => '2021-05-01 00:00:00',
    ]);
    $user2->prices()->create([
        'published_at' => '2021-04-01 00:00:00',
    ]);

    $users = HasOneOfManyTestUser::with('price')->get();

    expect($users[0]->price)->not->toBeNull();
    expect($users[0]->price->id)->toBe($user1Price->id);

    expect($users[1]->price)->not->toBeNull();
    expect($users[1]->price->id)->toBe($user2Price->id);
});

test('with exists', function () {
    $user = HasOneOfManyTestUser::create();

    $user = HasOneOfManyTestUser::withExists('latest_login')->first();
    expect($user->latest_login_exists)->toBeFalse();

    $user->logins()->create();
    $user = HasOneOfManyTestUser::withExists('latest_login')->first();
    expect($user->latest_login_exists)->toBeTrue();
});

test('with exists with constraints in join sub select', function () {
    $user = HasOneOfManyTestUser::create();

    $user = HasOneOfManyTestUser::withExists('foo_state')->first();

    expect($user->foo_state_exists)->toBeFalse();

    $user->states()->create([
        'type' => 'foo',
        'state' => 'bar',
    ]);
    $user = HasOneOfManyTestUser::withExists('foo_state')->first();
    expect($user->foo_state_exists)->toBeTrue();
});

test('with soft deletes', function () {
    $user = HasOneOfManyTestUser::create();
    $user->logins()->create();
    $user->latest_login_with_soft_deletes;
    expect($user->latest_login_with_soft_deletes)->not->toBeNull();
});

test('with constraint not in aggregate', function () {
    $user = HasOneOfManyTestUser::create();

    $previousFoo = $user->states()->create([
        'type' => 'foo',
        'state' => 'bar',
        'updated_at' => '2020-01-01 00:00:00',
    ]);
    $newFoo = $user->states()->create([
        'type' => 'foo',
        'state' => 'active',
        'updated_at' => '2021-01-01 12:00:00',
    ]);
    $newBar = $user->states()->create([
        'type' => 'bar',
        'state' => 'active',
        'updated_at' => '2021-01-01 12:00:00',
    ]);

    expect($user->last_updated_foo_state->id)->toBe($newFoo->id);
});

test('it gets correct result using at least two aggregates distinct from id', function () {
    $user = HasOneOfManyTestUser::create();

    $expectedState = $user->states()->create([
        'state' => 'state',
        'type' => 'type',
        'created_at' => '2023-01-01',
        'updated_at' => '2023-01-03',
    ]);

    $user->states()->create([
        'state' => 'state',
        'type' => 'type',
        'created_at' => '2023-01-01',
        'updated_at' => '2023-01-02',
    ]);

    expect($user->latest_updated_latest_created_state->id)->toBe($expectedState->id);
});

/**
 * Instrument Models...
 */
class HasOneOfManyTestUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;

    public function logins()
    {
        return $this->hasMany(HasOneOfManyTestLogin::class, 'user_id');
    }

    public function latest_login()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany();
    }

    public function latest_login_with_soft_deletes()
    {
        return $this->hasOne(HasOneOfManyTestLoginWithSoftDeletes::class, 'user_id')->ofMany();
    }

    public function latest_login_with_shortcut()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->latestOfMany();
    }

    public function latest_login_with_invalid_aggregate()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany('id', 'count');
    }

    public function latest_login_without_global_scope()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->withoutGlobalScopes()->latestOfMany();
    }

    public function first_login()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany('id', 'min');
    }

    public function latest_login_with_foo_state()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->join('states', 'states.user_id', 'logins.user_id')
                    ->where('states.type', 'foo');
            }
        );
    }

    public function states()
    {
        return $this->hasMany(HasOneOfManyTestState::class, 'user_id');
    }

    public function foo_state()
    {
        return $this->hasOne(HasOneOfManyTestState::class, 'user_id')->ofMany(
            [], // should automatically add 'id' => 'max'
            function ($q) {
                $q->where('type', 'foo');
            }
        );
    }

    public function last_updated_foo_state()
    {
        return $this->hasOne(HasOneOfManyTestState::class, 'user_id')->ofMany([
            'updated_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('type', 'foo');
        });
    }

    public function prices()
    {
        return $this->hasMany(HasOneOfManyTestPrice::class, 'user_id');
    }

    public function price()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->ofMany([
            'published_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('published_at', '<', now());
        });
    }

    public function price_without_key_in_aggregates()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->ofMany(['published_at' => 'MAX']);
    }

    public function price_with_shortcut()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->latestOfMany(['published_at', 'id']);
    }

    public function price_without_global_scope()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->withoutGlobalScopes()->ofMany([
            'published_at' => 'max',
            'id' => 'max',
        ], function ($q) {
            $q->where('published_at', '<', now());
        });
    }

    public function latest_updated_latest_created_state()
    {
        return $this->hasOne(HasOneOfManyTestState::class, 'user_id')->ofMany([
            'updated_at' => 'max',
            'created_at' => 'max',
        ]);
    }
}

class HasOneOfManyTestModel extends Instrument
{
    public function logins()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class)->ofMany();
    }
}

class HasOneOfManyTestLogin extends Instrument
{
    protected $table = 'logins';
    protected $guarded = [];
    public $timestamps = false;
}

class HasOneOfManyTestLoginWithSoftDeletes extends Instrument
{
    use SoftDeletes;

    protected $table = 'logins';
    protected $guarded = [];
    public $timestamps = false;
}

class HasOneOfManyTestState extends Instrument
{
    protected $table = 'states';
    protected $guarded = [];
    public $timestamps = true;
    protected $fillable = ['type', 'state', 'updated_at'];
}

class HasOneOfManyTestPrice extends Instrument
{
    protected $table = 'prices';
    protected $guarded = [];
    public $timestamps = false;
    protected $fillable = ['published_at'];
    protected $casts = ['published_at' => 'datetime'];
}
