<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use RuntimeException;

function dbTimestampsCreateSchema()
{
    dbTimestampsSchema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->timestamps();
    });

    dbTimestampsSchema()->create('users_created_at', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->string('created_at');
    });

    dbTimestampsSchema()->create('users_updated_at', function ($table) {
        $table->increments('id');
        $table->string('email')->unique();
        $table->string('updated_at');
    });
}

function dbTimestampsConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

function dbTimestampsSchema()
{
    return dbTimestampsConnection()->getSchemaBuilder();
}

beforeEach(function () {
    parent::setUp();

    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    dbTimestampsCreateSchema();
});

afterEach(function () {
    dbTimestampsSchema()->drop('users');
    dbTimestampsSchema()->drop('users_created_at');
    dbTimestampsSchema()->drop('users_updated_at');
    Carbon::setTestNow(null);

    parent::tearDown();
});

test('user with created at and updated at', function () {
    Carbon::setTestNow($now = Carbon::now());

    $user = UserWithCreatedAndUpdated::create([
        'email' => 'test@test.com',
    ]);

    $this->assertEquals($now->toDateTimeString(), $user->created_at->toDateTimeString());
    $this->assertEquals($now->toDateTimeString(), $user->updated_at->toDateTimeString());
});

test('user with created at', function () {
    Carbon::setTestNow($now = Carbon::now());

    $user = UserWithCreated::create([
        'email' => 'test@test.com',
    ]);

    $this->assertEquals($now->toDateTimeString(), $user->created_at->toDateTimeString());
});

test('user with updated at', function () {
    Carbon::setTestNow($now = Carbon::now());

    $user = UserWithUpdated::create([
        'email' => 'test@test.com',
    ]);

    $this->assertEquals($now->toDateTimeString(), $user->updated_at->toDateTimeString());
});

test('without timestamp', function () {
    Carbon::setTestNow($now = Carbon::now()->setYear(1995)->startOfYear());
    $user = UserWithCreatedAndUpdated::create(['email' => 'foo@example.com']);
    Carbon::setTestNow(Carbon::now()->addHour());

    $this->assertTrue($user->usesTimestamps());

    $user->withoutTimestamps(function () use ($user) {
        $this->assertFalse($user->usesTimestamps());

        $user->withoutTimestamps(function () use ($user) {
            $this->assertFalse($user->usesTimestamps());
        });

        $this->assertFalse($user->usesTimestamps());
        $user->update([
            'email' => 'bar@example.com',
        ]);
    });

    $this->assertTrue($user->usesTimestamps());
    $this->assertTrue($now->equalTo($user->updated_at));
    $this->assertSame('bar@example.com', $user->email);
});

test('without timestamp when already ignoring timestamps', function () {
    Carbon::setTestNow($now = Carbon::now()->setYear(1995)->startOfYear());
    $user = UserWithCreatedAndUpdated::create(['email' => 'foo@example.com']);
    Carbon::setTestNow(Carbon::now()->addHour());

    $user->timestamps = false;

    $this->assertFalse($user->usesTimestamps());

    $user->withoutTimestamps(function () use ($user) {
        $this->assertFalse($user->usesTimestamps());
        $user->update([
            'email' => 'bar@example.com',
        ]);
    });

    $this->assertFalse($user->usesTimestamps());
    $this->assertTrue($now->equalTo($user->updated_at));
    $this->assertSame('bar@example.com', $user->email);
});

test('without timestamp restores when closure throws exception', function () {
    $user = UserWithCreatedAndUpdated::create(['email' => 'foo@example.com']);

    $user->timestamps = true;

    try {
        $user->withoutTimestamps(function () use ($user) {
            $this->assertFalse($user->usesTimestamps());
            throw new RuntimeException();
        });
        $this->fail();
    } catch (RuntimeException) {
        //
    }

    $this->assertTrue($user->timestamps);
});

test('without timestamps respects classes', function () {
    $a = new UserWithCreatedAndUpdated();
    $b = new UserWithCreatedAndUpdated();
    $z = new UserWithUpdated();

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    Instrument::withoutTimestamps(function () use ($a, $b, $z) {
        $this->assertFalse($a->usesTimestamps());
        $this->assertFalse($b->usesTimestamps());
        $this->assertFalse($z->usesTimestamps());
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    UserWithCreatedAndUpdated::withoutTimestamps(function () use ($a, $b, $z) {
        $this->assertFalse($a->usesTimestamps());
        $this->assertFalse($b->usesTimestamps());
        $this->assertTrue($z->usesTimestamps());
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    UserWithUpdated::withoutTimestamps(function () use ($a, $b, $z) {
        $this->assertTrue($a->usesTimestamps());
        $this->assertTrue($b->usesTimestamps());
        $this->assertFalse($z->usesTimestamps());
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    Instrument::withoutTimestampsOn([], function () use ($a, $b, $z) {
        $this->assertTrue($a->usesTimestamps());
        $this->assertTrue($b->usesTimestamps());
        $this->assertTrue($z->usesTimestamps());
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    Instrument::withoutTimestampsOn([UserWithCreatedAndUpdated::class], function () use ($a, $b, $z) {
        $this->assertFalse($a->usesTimestamps());
        $this->assertFalse($b->usesTimestamps());
        $this->assertTrue($z->usesTimestamps());
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    Instrument::withoutTimestampsOn([UserWithUpdated::class], function () use ($a, $b, $z) {
        $this->assertTrue($a->usesTimestamps());
        $this->assertTrue($b->usesTimestamps());
        $this->assertFalse($z->usesTimestamps());
        $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));

    Instrument::withoutTimestampsOn([UserWithCreatedAndUpdated::class, UserWithUpdated::class], function () use ($a, $b, $z) {
        $this->assertFalse($a->usesTimestamps());
        $this->assertFalse($b->usesTimestamps());
        $this->assertFalse($z->usesTimestamps());
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
        $this->assertTrue(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
    });

    $this->assertTrue($a->usesTimestamps());
    $this->assertTrue($b->usesTimestamps());
    $this->assertTrue($z->usesTimestamps());
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithCreatedAndUpdated::class));
    $this->assertFalse(Instrument::isIgnoringTimestamps(UserWithUpdated::class));
});

/**
 * Instrument Models...
 */
class UserWithCreatedAndUpdated extends Instrument
{
    protected $table = 'users';

    protected $guarded = [];
}

class UserWithCreated extends Instrument
{
    public const UPDATED_AT = null;

    protected $table = 'users_created_at';

    protected $guarded = [];

    protected $dateFormat = 'U';
}

class UserWithUpdated extends Instrument
{
    public const CREATED_AT = null;

    protected $table = 'users_updated_at';

    protected $guarded = [];

    protected $dateFormat = 'U';
}
