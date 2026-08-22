<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Connection;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Schema\Builder as SchemaBuilder;
use Voyager\Events\Dispatcher;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Queue\DatabaseQueue;
use Voyager\Queue\Events\JobQueued;
use Voyager\Queue\Events\JobQueueing;
use Voyager\Vessel\Vessel;

function deferredDatabaseQueueConnection(): Connection
{
    return Instrument::getConnectionResolver()->connection();
}

function deferredDatabaseQueueSchema(): SchemaBuilder
{
    return deferredDatabaseQueueConnection()->getSchemaBuilder();
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();

    $db->setAsGlobal();

    $this->table = 'jobs';

    $this->queue = new DatabaseQueue(deferredDatabaseQueueConnection(), $this->table);

    $this->container = new Vessel;

    $this->container->instance('events', new Dispatcher($this->container));

    $this->queue->setContainer($this->container);

    deferredDatabaseQueueSchema()->create($this->table, function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue');
        $table->longText('payload');
        $table->tinyInteger('attempts')->unsigned();
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
        $table->index(['queue', 'reserved_at']);
    });
});

afterEach(function () {
    deferredDatabaseQueueSchema()->drop('jobs');
});

test('available and un reserved jobs are popped', function () {
    deferredDatabaseQueueConnection()
        ->table('jobs')
        ->insert([
            'id' => 1,
            'queue' => $mock_queue_name = 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->subSeconds(1)->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ]);

    $popped_job = $this->queue->pop($mock_queue_name);

    expect($popped_job)->not->toBeNull();
});

test('popped jobs increment attempts', function () {
    $job = [
        'id' => 1,
        'queue' => 'mock_queue_name',
        'payload' => 'mock_payload',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => Carbon::now()->subSeconds(1)->getTimestamp(),
        'created_at' => Carbon::now()->getTimestamp(),
    ];

    deferredDatabaseQueueConnection()->table('jobs')->insert($job);

    $popped_job = $this->queue->pop($job['queue']);

    $database_record = deferredDatabaseQueueConnection()->table('jobs')->find($job['id']);

    expect($database_record->attempts)->toBe(1);
    expect($popped_job->attempts())->toBe(1);
});

test('that queue can be cleared', function () {
    deferredDatabaseQueueConnection()
        ->table('jobs')
        ->insert([[
            'id' => 1,
            'queue' => $mock_queue_name = 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved_at' => Carbon::now()->addDay()->getTimestamp(),
            'available_at' => Carbon::now()->subDay()->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ], [
            'id' => 2,
            'queue' => $mock_queue_name,
            'payload' => 'mock_payload 2',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->subSeconds(1)->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ]]);

    expect($this->queue->clear($mock_queue_name))->toBe(2);
    expect($this->queue->size())->toBe(0);
});

test('unavailable jobs are not popped', function () {
    deferredDatabaseQueueConnection()
        ->table('jobs')
        ->insert([
            'id' => 1,
            'queue' => $mock_queue_name = 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->addSeconds(60)->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ]);

    $popped_job = $this->queue->pop($mock_queue_name);

    expect($popped_job)->toBeNull();
});

test('that reserved and expired jobs are popped', function () {
    deferredDatabaseQueueConnection()
        ->table('jobs')
        ->insert([
            'id' => 1,
            'queue' => $mock_queue_name = 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved_at' => Carbon::now()->subDay()->getTimestamp(),
            'available_at' => Carbon::now()->addDay()->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ]);

    $popped_job = $this->queue->pop($mock_queue_name);

    expect($popped_job)->not->toBeNull();
});

test('that reserved jobs are not popped', function () {
    deferredDatabaseQueueConnection()
        ->table('jobs')
        ->insert([
            'id' => 1,
            'queue' => $mock_queue_name = 'mock_queue_name',
            'payload' => 'mock_payload',
            'attempts' => 0,
            'reserved_at' => Carbon::now()->addDay()->getTimestamp(),
            'available_at' => Carbon::now()->subDay()->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
        ]);

    $popped_job = $this->queue->pop($mock_queue_name);

    expect($popped_job)->toBeNull();
});

test('job payload is available on events', function () {
    $jobQueueingEvent = null;
    $jobQueuedEvent = null;
    Str::createUuidsUsingSequence([
        'expected-job-uuid',
    ]);
    $this->container['events']->listen(function (JobQueueing $e) use (&$jobQueueingEvent) {
        $jobQueueingEvent = $e;
    });
    $this->container['events']->listen(function (JobQueued $e) use (&$jobQueuedEvent) {
        $jobQueuedEvent = $e;
    });

    $this->queue->push('MyJob', [
        'laravel' => 'Framework',
    ]);

    expect($jobQueueingEvent->payload())->toBeArray();
    expect($jobQueueingEvent->payload()['uuid'])->toBe('expected-job-uuid');

    expect($jobQueuedEvent->payload())->toBeArray();
    expect($jobQueuedEvent->payload()['uuid'])->toBe('expected-job-uuid');
});
