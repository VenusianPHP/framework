<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\Relations\BelongsToMany;

function dbBtmOrFailCreateSchema()
{
    dbBtmOrFailSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email');
    });

    dbBtmOrFailSchema()->create('roles', function ($table) {
        $table->increments('id');
        $table->string('name');
    });

    dbBtmOrFailSchema()->create('role_user', function ($table) {
        $table->integer('user_id')->unsigned();
        $table->integer('role_id')->unsigned();
        $table->boolean('active')->default(false);
    });
}

/**
 * Get a database connection instance.
 *
 * @return \Voyager\Database\Connection
 */
function dbBtmOrFailConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 *
 * @return \Voyager\Database\Schema\Builder
 */
function dbBtmOrFailSchema()
{
    return dbBtmOrFailConnection()->getSchemaBuilder();
}

function dbBtmOrFailSeedData()
{
    OrFailUser::create(['id' => 1, 'email' => 'taylor@laravel.com']);
    OrFailRole::insert([
        ['id' => 1, 'name' => 'Admin'],
        ['id' => 2, 'name' => 'Editor'],
        ['id' => 3, 'name' => 'Viewer'],
    ]);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbBtmOrFailCreateSchema();
});

afterEach(function () {
    dbBtmOrFailSchema()->drop('users');
    dbBtmOrFailSchema()->drop('roles');
    dbBtmOrFailSchema()->drop('role_user');
});

test('sync or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);

    $result = $user->roles()->syncOrFail([1, 2]);

    expect($result['attached'])->toEqual([1, 2]);
    expect($result['detached'])->toBeEmpty();
    expect($result['updated'])->toBeEmpty();
    expect($user->roles)->toHaveCount(2);
});

test('sync without detaching or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attach([1]);

    $result = $user->roles()->syncWithoutDetachingOrFail([2, 3]);

    expect($result['attached'])->toEqual([2, 3]);
    expect($result['detached'])->toBeEmpty();
    expect($user->roles()->get())->toHaveCount(3);
});

test('attach or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);

    $user->roles()->attachOrFail(1);

    expect($user->roles)->toHaveCount(1);
});

test('attach or fail with attributes', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attachOrFail(1, ['active' => true]);

    $pivot = DB::table('role_user')->where('user_id', 1)->where('role_id', 1)->first();
    expect($pivot->active)->toEqual(1);
});

test('detach or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attach([1, 2, 3]);

    $result = $user->roles()->detachOrFail([1, 2]);

    expect($result)->toEqual(2);
    expect($user->roles()->get())->toHaveCount(1);
});

test('detach or fail all', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attach([1, 2, 3]);

    $result = $user->roles()->detachOrFail();

    expect($result)->toEqual(3);
    expect($user->roles()->get())->toHaveCount(0);
});

test('toggle or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attach([1]);

    $result = $user->roles()->toggleOrFail([1, 2]);

    expect($result['detached'])->toEqual([1]);
    expect($result['attached'])->toEqual([2]);
    expect($user->roles()->get())->toHaveCount(1);
});

test('sync with pivot values or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);

    $result = $user->roles()->syncWithPivotValuesOrFail([1, 2], ['active' => true]);

    expect($result['attached'])->toEqual([1, 2]);
    expect($result['detached'])->toBeEmpty();
    expect($result['updated'])->toBeEmpty();

    $pivot = DB::table('role_user')->where('user_id', 1)->where('role_id', 1)->first();
    expect($pivot->active)->toEqual(1);
});

test('update existing pivot or fail', function () {
    dbBtmOrFailSeedData();

    $user = OrFailUser::find(1);
    $user->roles()->attach(1, ['active' => false]);

    $result = $user->roles()->updateExistingPivotOrFail(1, ['active' => true]);

    expect($result)->toEqual(1);

    $pivot = DB::table('role_user')->where('user_id', 1)->where('role_id', 1)->first();
    expect($pivot->active)->toEqual(1);
});

class OrFailUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(OrFailRole::class, 'role_user', 'user_id', 'role_id');
    }
}

class OrFailRole extends Instrument
{
    protected $table = 'roles';
    protected $guarded = [];
    public $timestamps = false;
}
