<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Schema\Blueprint;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Queue\Failed\DatabaseUuidFailedJobProvider;

function databaseUuidFailedJobProvider(string $database = 'default', string $table = 'failed_jobs')
{
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->getConnection()->getSchemaBuilder()->create('failed_jobs', function (Blueprint $table) {
        $table->uuid();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });

    return new DatabaseUuidFailedJobProvider($db->getDatabaseManager(), $database, $table);
}

test('getting ids of all failed jobs', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());
    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-2']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-3']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-4']), new RuntimeException());

    expect($provider->ids())->toBe(['uuid-1', 'uuid-2', 'uuid-3', 'uuid-4']);
    expect($provider->ids('queue-1'))->toBe(['uuid-1', 'uuid-2']);
    expect($provider->ids('queue-2'))->toBe(['uuid-3', 'uuid-4']);
});

test('getting all failed jobs', function () {
    $provider = databaseUuidFailedJobProvider();

    expect($provider->all())->toBeEmpty();

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());
    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-2']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-3']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-4']), new RuntimeException());

    expect($provider->all())->toHaveCount(4);
    expect(array_column($provider->all(), 'id'))->toBe(['uuid-1', 'uuid-2', 'uuid-3', 'uuid-4']);
});

test('finding failed jobs by id', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());

    expect($provider->find('uuid-2'))->toBeNull();
    expect($provider->find('uuid-1')->id)->toEqual('uuid-1');
    expect($provider->find('uuid-1')->queue)->toEqual('queue-1');
    expect($provider->find('uuid-1')->connection)->toEqual('connection-1');
});

test('removing jobs by id', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());

    expect($provider->find('uuid-1'))->not->toBeNull();

    $provider->forget('uuid-1');

    expect($provider->find('uuid-1'))->toBeNull();
});

test('removing all failed jobs', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-2']), new RuntimeException());

    expect($provider->all())->toHaveCount(2);

    $provider->flush();

    expect($provider->all())->toBeEmpty();
});

test('pruning failed jobs', function () {
    $provider = databaseUuidFailedJobProvider();

    Carbon::setTestNow(Carbon::createFromDate(2024, 4, 28));

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-2']), new RuntimeException());

    $provider->prune(Carbon::createFromDate(2024, 4, 26));

    expect($provider->all())->toHaveCount(2);

    $provider->prune(Carbon::createFromDate(2024, 4, 30));

    expect($provider->all())->toBeEmpty();
});

test('pruning failed jobs with relative hours and minutes', function () {
    $provider = databaseUuidFailedJobProvider();

    Carbon::setTestNow(Carbon::create(2025, 8, 24, 12, 30, 0));

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => 'uuid-1']), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => 'uuid-2']), new RuntimeException());

    $provider->prune(Carbon::create(2025, 8, 24, 12, 30, 0));

    expect($provider->all())->toHaveCount(2);

    $provider->prune(Carbon::create(2025, 8, 24, 13, 0, 0));

    expect($provider->all())->toBeEmpty();
});

test('jobs can be counted', function () {
    $provider = databaseUuidFailedJobProvider();

    expect($provider->count())->toBe(0);

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count())->toBe(1);

    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-2', 'queue-2', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count())->toBe(3);
});

test('jobs can be counted by connection', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-2', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count('connection-1'))->toBe(1);
    expect($provider->count('connection-2'))->toBe(1);

    $provider->log('connection-1', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count('connection-1'))->toBe(2);
    expect($provider->count('connection-2'))->toBe(1);
});

test('jobs can be counted by queue', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('database', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('database', 'queue-2', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count(queue: 'queue-1'))->toBe(1);
    expect($provider->count(queue: 'queue-2'))->toBe(1);

    $provider->log('database', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    expect($provider->count(queue: 'queue-1'))->toBe(2);
    expect($provider->count(queue: 'queue-2'))->toBe(1);
});

test('jobs can be counted by queue and connection', function () {
    $provider = databaseUuidFailedJobProvider();

    $provider->log('connection-1', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-1', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-2', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-1', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-2', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());
    $provider->log('connection-2', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException());

    expect($provider->count('connection-1', 'queue-99'))->toBe(2);
    expect($provider->count('connection-2', 'queue-99'))->toBe(1);
    expect($provider->count('connection-1', 'queue-1'))->toBe(1);
    expect($provider->count('connection-2', 'queue-1'))->toBe(2);
});
