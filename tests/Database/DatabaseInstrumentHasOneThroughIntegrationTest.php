<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\Instrument\SoftDeletes;

function dbHasOneThroughIntegrationConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

function dbHasOneThroughIntegrationSchema()
{
    return dbHasOneThroughIntegrationConnection()->getSchemaBuilder();
}

function dbHasOneThroughIntegrationCreateSchema()
{
    dbHasOneThroughIntegrationSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->unsignedInteger('position_id')->unique()->nullable();
        $table->string('position_short');
        $table->timestamps();
        $table->softDeletes();
    });

    dbHasOneThroughIntegrationSchema()->create('contracts', function ($table) {
        $table->increments('id');
        $table->integer('user_id')->unique();
        $table->string('title');
        $table->text('body');
        $table->string('email');
        $table->timestamps();
    });

    dbHasOneThroughIntegrationSchema()->create('positions', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('shortname');
        $table->timestamps();
    });
}

/**
 * Helpers...
 */
function dbHasOneThroughIntegrationSeedData()
{
    HasOneThroughTestPosition::create(['id' => 1, 'name' => 'President', 'shortname' => 'ps'])
        ->user()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'position_short' => 'ps'])
        ->contract()->create(['title' => 'A title', 'body' => 'A body', 'email' => 'taylorotwell@gmail.com']);
}

function dbHasOneThroughIntegrationSeedDataExtended()
{
    $position = HasOneThroughTestPosition::create(['id' => 2, 'name' => 'Vice President', 'shortname' => 'vp']);
    $position->user()->create(['id' => 2, 'email' => 'example1@gmail.com', 'position_short' => 'vp'])
        ->contract()->create(
            ['title' => 'Example1 title1', 'body' => 'Example1 body1', 'email' => 'example1contract1@gmail.com']
        );
}

/**
 * Seed data for a default HasOneThrough setup.
 */
function dbHasOneThroughIntegrationSeedDefaultData()
{
    HasOneThroughDefaultTestPosition::create(['id' => 1, 'name' => 'President'])
        ->user()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com'])
        ->contract()->create(['title' => 'A title', 'body' => 'A body']);
}

/**
 * Drop the default tables.
 */
function dbHasOneThroughIntegrationResetDefault()
{
    dbHasOneThroughIntegrationSchema()->drop('users_default');
    dbHasOneThroughIntegrationSchema()->drop('contracts_default');
    dbHasOneThroughIntegrationSchema()->drop('positions_default');
}

/**
 * Migrate tables for classes with a Laravel "default" HasOneThrough setup.
 */
function dbHasOneThroughIntegrationMigrateDefault()
{
    dbHasOneThroughIntegrationSchema()->create('users_default', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->unsignedInteger('has_one_through_default_test_position_id')->unique()->nullable();
        $table->timestamps();
    });

    dbHasOneThroughIntegrationSchema()->create('contracts_default', function ($table) {
        $table->increments('id');
        $table->integer('has_one_through_default_test_user_id')->unique();
        $table->string('title');
        $table->text('body');
        $table->timestamps();
    });

    dbHasOneThroughIntegrationSchema()->create('positions_default', function ($table) {
        $table->increments('id');
        $table->string('name');
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

    dbHasOneThroughIntegrationCreateSchema();
});

afterEach(function () {
    dbHasOneThroughIntegrationSchema()->drop('users');
    dbHasOneThroughIntegrationSchema()->drop('contracts');
    dbHasOneThroughIntegrationSchema()->drop('positions');
});

test('it loads a has one through relation with custom keys', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughTestPosition::first()->contract;

    expect($contract->title)->toBe('A title');
});

test('it loads a default has one through relation', function () {
    dbHasOneThroughIntegrationMigrateDefault();
    dbHasOneThroughIntegrationSeedDefaultData();

    $contract = HasOneThroughDefaultTestPosition::first()->contract;
    expect($contract->title)->toBe('A title');
    $this->assertArrayNotHasKey('email', $contract->getAttributes());

    dbHasOneThroughIntegrationResetDefault();
});

test('it loads a relation with custom intermediate and local key', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughIntermediateTestPosition::first()->contract;

    expect($contract->title)->toBe('A title');
});

test('eager loading a relation with custom intermediate and local key', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughIntermediateTestPosition::with('contract')->first()->contract;

    expect($contract->title)->toBe('A title');
});

test('where has on a relation with custom intermediate and local key', function () {
    dbHasOneThroughIntegrationSeedData();
    $position = HasOneThroughIntermediateTestPosition::whereHas('contract', function ($query) {
        $query->where('title', 'A title');
    })->get();

    expect($position)->toHaveCount(1);
});

test('with where has on a relation with custom intermediate and local key', function () {
    dbHasOneThroughIntegrationSeedData();
    $position = HasOneThroughIntermediateTestPosition::withWhereHas('contract', function ($query) {
        $query->where('title', 'A title');
    })->get();

    expect($position)->toHaveCount(1);
    expect($position->first()->relationLoaded('contract'))->toBeTrue();
    $this->assertEquals($position->first()->contract->pluck('title')->unique()->toArray(), ['A title']);
});

test('first or fail throws an exception', function () {
    HasOneThroughTestPosition::create(['id' => 1, 'name' => 'President', 'shortname' => 'ps'])
        ->user()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'position_short' => 'ps']);

    HasOneThroughTestPosition::first()->contract()->firstOrFail();
})->throws(ModelNotFoundException::class, 'No query results for model [Tests\Database\HasOneThroughTestContract].');

test('find or fail throws an exception', function () {
    HasOneThroughTestPosition::create(['id' => 1, 'name' => 'President', 'shortname' => 'ps'])
        ->user()->create(['id' => 1, 'email' => 'taylorotwell@gmail.com', 'position_short' => 'ps']);

    HasOneThroughTestPosition::first()->contract()->findOrFail(1);
})->throws(ModelNotFoundException::class);

test('first retrieves first record', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughTestPosition::first()->contract()->first();

    expect($contract)->not->toBeNull();
    expect($contract->title)->toBe('A title');
});

test('all columns are retrieved by default', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughTestPosition::first()->contract()->first();
    expect(array_keys($contract->getAttributes()))->toEqual([
        'id',
        'user_id',
        'title',
        'body',
        'email',
        'created_at',
        'updated_at',
        'laravel_through_key',
    ]);
});

test('only proper columns are selected if provided', function () {
    dbHasOneThroughIntegrationSeedData();
    $contract = HasOneThroughTestPosition::first()->contract()->first(['title', 'body']);

    expect(array_keys($contract->getAttributes()))->toEqual([
        'title',
        'body',
        'laravel_through_key',
    ]);
});

test('chunk returns correct models', function () {
    dbHasOneThroughIntegrationSeedData();
    dbHasOneThroughIntegrationSeedDataExtended();
    $position = HasOneThroughTestPosition::find(1);

    $position->contract()->chunk(10, function ($contractsChunk) {
        $contract = $contractsChunk->first();
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key', ], array_keys($contract->getAttributes()));
    });
});

test('cursor returns correct models', function () {
    dbHasOneThroughIntegrationSeedData();
    dbHasOneThroughIntegrationSeedDataExtended();
    $position = HasOneThroughTestPosition::find(1);

    $contracts = $position->contract()->cursor();

    foreach ($contracts as $contract) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key', ], array_keys($contract->getAttributes()));
    }
});

test('each returns correct models', function () {
    dbHasOneThroughIntegrationSeedData();
    dbHasOneThroughIntegrationSeedDataExtended();
    $position = HasOneThroughTestPosition::find(1);

    $position->contract()->each(function ($contract) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key', ], array_keys($contract->getAttributes()));
    });
});

test('lazy returns correct models', function () {
    dbHasOneThroughIntegrationSeedData();
    dbHasOneThroughIntegrationSeedDataExtended();
    $position = HasOneThroughTestPosition::find(1);

    $position->contract()->lazy()->each(function ($contract) {
        $this->assertEquals([
            'id',
            'user_id',
            'title',
            'body',
            'email',
            'created_at',
            'updated_at',
            'laravel_through_key', ], array_keys($contract->getAttributes()));
    });
});

test('intermediate soft deletes are ignored', function () {
    dbHasOneThroughIntegrationSeedData();
    HasOneThroughSoftDeletesTestUser::first()->delete();

    $contract = HasOneThroughSoftDeletesTestPosition::first()->contract;

    expect($contract->title)->toBe('A title');
});

test('eager loading loads related models correctly', function () {
    dbHasOneThroughIntegrationSeedData();
    $position = HasOneThroughSoftDeletesTestPosition::with('contract')->first();

    expect($position->shortname)->toBe('ps');
    expect($position->contract->title)->toBe('A title');
});

/**
 * Instrument Models...
 */
class HasOneThroughTestUser extends Instrument
{
    protected $table = 'users';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOne(HasOneThroughTestContract::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class HasOneThroughTestContract extends Instrument
{
    protected $table = 'contracts';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasOneThroughTestUser::class, 'user_id');
    }
}

class HasOneThroughTestPosition extends Instrument
{
    protected $table = 'positions';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOneThrough(HasOneThroughTestContract::class, HasOneThroughTestUser::class, 'position_id', 'user_id');
    }

    public function user()
    {
        return $this->hasOne(HasOneThroughTestUser::class, 'position_id');
    }
}

/**
 * Instrument Models...
 */
class HasOneThroughDefaultTestUser extends Instrument
{
    protected $table = 'users_default';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOne(HasOneThroughDefaultTestContract::class);
    }
}

/**
 * Instrument Models...
 */
class HasOneThroughDefaultTestContract extends Instrument
{
    protected $table = 'contracts_default';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasOneThroughDefaultTestUser::class);
    }
}

class HasOneThroughDefaultTestPosition extends Instrument
{
    protected $table = 'positions_default';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOneThrough(HasOneThroughDefaultTestContract::class, HasOneThroughDefaultTestUser::class);
    }

    public function user()
    {
        return $this->hasOne(HasOneThroughDefaultTestUser::class);
    }
}

class HasOneThroughIntermediateTestPosition extends Instrument
{
    protected $table = 'positions';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOneThrough(HasOneThroughTestContract::class, HasOneThroughTestUser::class, 'position_short', 'email', 'shortname', 'email');
    }

    public function user()
    {
        return $this->hasOne(HasOneThroughTestUser::class, 'position_id');
    }
}

class HasOneThroughSoftDeletesTestUser extends Instrument
{
    use SoftDeletes;

    protected $table = 'users';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOne(HasOneThroughSoftDeletesTestContract::class, 'user_id');
    }
}

/**
 * Instrument Models...
 */
class HasOneThroughSoftDeletesTestContract extends Instrument
{
    protected $table = 'contracts';
    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(HasOneThroughSoftDeletesTestUser::class, 'user_id');
    }
}

class HasOneThroughSoftDeletesTestPosition extends Instrument
{
    protected $table = 'positions';
    protected $guarded = [];

    public function contract()
    {
        return $this->hasOneThrough(HasOneThroughSoftDeletesTestContract::class, HasOneThroughTestUser::class, 'position_id', 'user_id');
    }

    public function user()
    {
        return $this->hasOne(HasOneThroughSoftDeletesTestUser::class, 'position_id');
    }
}
