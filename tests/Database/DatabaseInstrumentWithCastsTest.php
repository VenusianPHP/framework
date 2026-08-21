<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\MissingAttributeException;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;

/**
 * Get a database connection instance.
 */
function instrumentWithCastsConnection()
{
    return Instrument::getConnectionResolver()->connection();
}

/**
 * Get a schema builder instance.
 */
function instrumentWithCastsSchema()
{
    return instrumentWithCastsConnection()->getSchemaBuilder();
}

function instrumentWithCastsCreateSchema()
{
    instrumentWithCastsSchema()->create('times', function ($table) {
        $table->increments('id');
        $table->time('time');
        $table->timestamps();
    });

    instrumentWithCastsSchema()->create('unique_times', function ($table) {
        $table->increments('id');
        $table->time('time')->unique();
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

    instrumentWithCastsCreateSchema();
});

test('with first or new', function () {
    $time1 = Time::query()->withCasts(['time' => 'string'])
        ->firstOrNew(['time' => '07:30']);

    Time::query()->insert(['time' => '07:30']);

    $time2 = Time::query()->withCasts(['time' => 'string'])
        ->firstOrNew(['time' => '07:30']);

    $this->assertSame('07:30', $time1->time);
    $this->assertSame($time1->time, $time2->time);
});

test('with first or create', function () {
    $time1 = Time::query()->withCasts(['time' => 'string'])
        ->firstOrCreate(['time' => '07:30']);

    $time2 = Time::query()->withCasts(['time' => 'string'])
        ->firstOrCreate(['time' => '07:30']);

    $this->assertSame($time1->id, $time2->id);
});

test('with create or first', function () {
    $time1 = UniqueTime::query()->withCasts(['time' => 'string'])
        ->createOrFirst(['time' => '07:30']);

    $time2 = UniqueTime::query()->withCasts(['time' => 'string'])
        ->createOrFirst(['time' => '07:30']);

    $this->assertSame($time1->id, $time2->id);
});

test('throws exception if castable attribute was not retrieved and prevent missing attributes is enabled', function () {
    Time::create(['time' => now()]);
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $time = Time::query()->select('id')->first();
        $this->assertNull($time->time);
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
})->throws(MissingAttributeException::class);

class Time extends Instrument
{
    protected $guarded = [];

    protected $casts = [
        'time' => 'datetime',
    ];
}

class UniqueTime extends Instrument
{
    protected $guarded = [];

    protected $casts = [
        'time' => 'datetime',
    ];
}
