<?php

use Carbon\Carbon;
use Tests\Queue\Fixtures\MyBatchableJob;
use Tests\Queue\Fixtures\MyTestJob;
use Voyager\Database\Connection;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Queue\DatabaseQueue;
use Voyager\Queue\Queue;
use Voyager\Vessel\Vessel;
use Mockery as m;

function deferredQueueDatabasePushJobs(): array
{
    $uuid = Str::uuid()->toString();

    return [
        [$uuid, new MyTestJob, 'MyTestJob', 'CallQueuedHandler'],
        [$uuid, fn () => 0, 'Closure', 'CallQueuedHandler'],
        [$uuid, 'foo', 'foo', 'foo'],
    ];
}

afterEach(function () {
    m::close();
});

test('push properly pushes job onto database', function (string $uuid, mixed $job, string $displayNameStartsWith, string $jobStartsWith) {
    Str::createUuidsUsing(function () use ($uuid) {
        return $uuid;
    });

    $queue = $this->getMockBuilder(DatabaseQueue::class)->onlyMethods(['currentTime'])->setConstructorArgs([$database = m::mock(Connection::class), 'table', 'default'])->getMock();
    $queue->expects($this->any())->method('currentTime')->willReturn('time');
    $queue->setContainer($container = m::spy(Vessel::class));
    $database->shouldReceive('table')->with('table')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) use ($uuid, $displayNameStartsWith, $jobStartsWith) {
        $payload = json_decode($array['payload'], true);
        expect($payload['uuid'])->toBe($uuid);
        expect($payload['displayName'])->toContain($displayNameStartsWith);
        expect($payload['job'])->toContain($jobStartsWith);

        expect($array['queue'])->toBe('default');
        expect($array['attempts'])->toEqual(0);
        expect($array['reserved_at'])->toBeNull();
        expect($array['available_at'])->toBeInt();
    });

    $queue->push($job, ['data']);

    $container->shouldHaveReceived('bound')->with('events')->twice();

    Str::createUuidsNormally();
})->with(deferredQueueDatabasePushJobs());

test('delayed push properly pushes job onto database', function () {
    $uuid = Str::uuid();

    Str::createUuidsUsing(function () use ($uuid) {
        return $uuid;
    });

    $time = Carbon::now();
    Carbon::setTestNow($time);

    $queue = $this->getMockBuilder(DatabaseQueue::class)
        ->onlyMethods(['currentTime'])
        ->setConstructorArgs([$database = m::mock(Connection::class), 'table', 'default'])
        ->getMock();
    $queue->expects($this->any())->method('currentTime')->willReturn('time');
    $queue->setContainer($container = m::spy(Vessel::class));
    $database->shouldReceive('table')->with('table')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) use ($uuid, $time) {
        expect($array['queue'])->toBe('default');
        expect($array['payload'])->toBe(json_encode(['uuid' => $uuid, 'displayName' => 'foo', 'job' => 'foo', 'maxTries' => null, 'maxExceptions' => null, 'failOnTimeout' => false, 'backoff' => null, 'timeout' => null, 'data' => ['data'], 'createdAt' => $time->getTimestamp(), 'delay' => 10]));
        expect($array['attempts'])->toEqual(0);
        expect($array['reserved_at'])->toBeNull();
        expect($array['available_at'])->toBeInt();
    });

    $queue->later(10, 'foo', ['data']);

    $container->shouldHaveReceived('bound')->with('events')->twice();

    Carbon::setTestNow();
    Str::createUuidsNormally();
});

test('push includes batch id in payload for batchable job', function () {
    $uuid = Str::uuid()->toString();

    Str::createUuidsUsing(function () use ($uuid) {
        return $uuid;
    });

    $job = (new MyBatchableJob)->withBatchId('test-batch-id');

    $queue = $this->getMockBuilder(DatabaseQueue::class)->onlyMethods(['currentTime'])->setConstructorArgs([$database = m::mock(Connection::class), 'table', 'default'])->getMock();
    $queue->expects($this->any())->method('currentTime')->willReturn('time');
    $queue->setContainer($container = m::spy(Vessel::class));
    $database->shouldReceive('table')->with('table')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
        $payload = json_decode($array['payload'], true);
        expect($payload['data']['batchId'])->toBe('test-batch-id');
    });

    $queue->push($job, ['data']);

    $container->shouldHaveReceived('bound')->with('events')->twice();

    Str::createUuidsNormally();
});

test('failure to create payload from object', function () {
    $job = new stdClass;
    $job->invalid = "\xc3\x28";

    $queue = m::mock(Queue::class)->makePartial();
    $class = new ReflectionClass(Queue::class);

    $createPayload = $class->getMethod('createPayload');
    $createPayload->invokeArgs($queue, [
        $job,
        'queue-name',
    ]);
})->throws(InvalidArgumentException::class);

test('failure to create payload from array', function () {
    $queue = m::mock(Queue::class)->makePartial();
    $class = new ReflectionClass(Queue::class);

    $createPayload = $class->getMethod('createPayload');
    $createPayload->invokeArgs($queue, [
        ["\xc3\x28"],
        'queue-name',
    ]);
})->throws(InvalidArgumentException::class);

test('bulk batch pushes onto database', function () {
    $uuid = Str::uuid();

    Str::createUuidsUsing(function () use ($uuid) {
        return $uuid;
    });

    $time = Carbon::now();
    Carbon::setTestNow($time);

    $database = m::mock(Connection::class);
    $queue = $this->getMockBuilder(DatabaseQueue::class)->onlyMethods(['currentTime', 'availableAt'])->setConstructorArgs([$database, 'table', 'default'])->getMock();
    $queue->expects($this->any())->method('currentTime')->willReturn('created');
    $queue->expects($this->any())->method('availableAt')->willReturn('available');
    $database->shouldReceive('table')->with('table')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('insert')->once()->andReturnUsing(function ($records) use ($uuid, $time) {
        expect($records)->toEqual([[
            'queue' => 'queue',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'foo', 'job' => 'foo', 'maxTries' => null, 'maxExceptions' => null, 'failOnTimeout' => false, 'backoff' => null, 'timeout' => null, 'data' => ['data'], 'createdAt' => $time->getTimestamp(), 'delay' => null]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 'available',
            'created_at' => 'created',
        ], [
            'queue' => 'queue',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'bar', 'job' => 'bar', 'maxTries' => null, 'maxExceptions' => null, 'failOnTimeout' => false, 'backoff' => null, 'timeout' => null, 'data' => ['data'], 'createdAt' => $time->getTimestamp(), 'delay' => null]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 'available',
            'created_at' => 'created',
        ]]);
    });

    $queue->bulk(['foo', 'bar'], ['data'], 'queue');

    Carbon::setTestNow();
    Str::createUuidsNormally();
});

test('build database record with payload at the end', function () {
    $queue = m::mock(DatabaseQueue::class);
    $record = $queue->buildDatabaseRecord('queue', 'any_payload', 0);
    expect($record)->toHaveKey('payload');
    expect(array_slice($record, -1, 1, true))->toHaveKey('payload');
});
