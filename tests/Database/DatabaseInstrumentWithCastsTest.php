<?php

namespace Tests\Database;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\MissingAttributeException;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Model as Instrument;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseInstrumentWithCastsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->schema()->create('times', function ($table) {
            $table->increments('id');
            $table->time('time');
            $table->timestamps();
        });

        $this->schema()->create('unique_times', function ($table) {
            $table->increments('id');
            $table->time('time')->unique();
            $table->timestamps();
        });
    }

    public function testWithFirstOrNew()
    {
        $time1 = Time::query()->withCasts(['time' => 'string'])
            ->firstOrNew(['time' => '07:30']);

        Time::query()->insert(['time' => '07:30']);

        $time2 = Time::query()->withCasts(['time' => 'string'])
            ->firstOrNew(['time' => '07:30']);

        $this->assertSame('07:30', $time1->time);
        $this->assertSame($time1->time, $time2->time);
    }

    public function testWithFirstOrCreate()
    {
        $time1 = Time::query()->withCasts(['time' => 'string'])
            ->firstOrCreate(['time' => '07:30']);

        $time2 = Time::query()->withCasts(['time' => 'string'])
            ->firstOrCreate(['time' => '07:30']);

        $this->assertSame($time1->id, $time2->id);
    }

    public function testWithCreateOrFirst()
    {
        $time1 = UniqueTime::query()->withCasts(['time' => 'string'])
            ->createOrFirst(['time' => '07:30']);

        $time2 = UniqueTime::query()->withCasts(['time' => 'string'])
            ->createOrFirst(['time' => '07:30']);

        $this->assertSame($time1->id, $time2->id);
    }

    public function testThrowsExceptionIfCastableAttributeWasNotRetrievedAndPreventMissingAttributesIsEnabled()
    {
        Time::create(['time' => now()]);
        $originalMode = Model::preventsAccessingMissingAttributes();
        Model::preventAccessingMissingAttributes();

        $this->expectException(MissingAttributeException::class);
        try {
            $time = Time::query()->select('id')->first();
            $this->assertNull($time->time);
        } finally {
            Model::preventAccessingMissingAttributes($originalMode);
        }
    }

    /**
     * Get a database connection instance.
     *
     * @return \Voyager\Database\Connection
     */
    protected function connection()
    {
        return Instrument::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Voyager\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

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
