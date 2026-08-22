<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\Database\Schema\Blueprint;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\MagicAliases\Date;
use Voyager\Queue\Failed\DatabaseFailedJobProvider;

function deferredFailedJobsTable(): QueryBuilder
{
    return test()->db->getConnection()->table('failed_jobs');
}

function deferredCreateFailedJobsRecord(array $overrides = []): bool
{
    return deferredFailedJobsTable()->insert(array_merge([
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => (string) Str::uuid()]),
        'exception' => new Exception('Whoops!'),
        'failed_at' => Date::now()->subDays(10),
    ], $overrides));
}

beforeEach(function () {
    $this->db = new DB;
    $this->db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $this->db->getConnection()->getSchemaBuilder()->create('failed_jobs', function (Blueprint $table) {
        $table->id();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });

    $this->provider = new DatabaseFailedJobProvider($this->db->getDatabaseManager(), 'default', 'failed_jobs');
});

test('can get all failed job ids', function () {
    expect($this->provider->ids())->toBeEmpty();

    array_map(fn () => deferredCreateFailedJobsRecord(), range(1, 4));

    expect($this->provider->ids())->toHaveCount(4)->toBe([4, 3, 2, 1]);
});

test('can get all failed jobs', function () {
    expect($this->provider->all())->toBeEmpty();

    array_map(fn () => deferredCreateFailedJobsRecord(), range(1, 4));

    $all = $this->provider->all();

    expect($all)->toHaveCount(4);
    expect($all[1]->id)->toBe(3);
    expect($all[1]->queue)->toBe('default');
});

test('can retrieve failed jobs by id', function () {
    array_map(fn () => deferredCreateFailedJobsRecord(), range(1, 2));

    expect($this->provider->find(1))->not->toBeNull();
    expect($this->provider->find(2))->not->toBeNull();
    expect($this->provider->find(3))->toBeNull();
});

test('can remove failed jobs by id', function () {
    deferredCreateFailedJobsRecord();

    expect($this->provider->forget(2))->toBeFalse();
    expect(deferredFailedJobsTable()->count())->toBe(1);
    expect($this->provider->forget(1))->toBeTrue();
    expect(deferredFailedJobsTable()->count())->toBe(0);
});

test('can prune failed jobs', function () {
    Carbon::setTestNow(Carbon::createFromDate(2024, 4, 28));

    deferredCreateFailedJobsRecord(['failed_at' => Carbon::createFromDate(2024, 4, 24)]);
    deferredCreateFailedJobsRecord(['failed_at' => Carbon::createFromDate(2024, 4, 26)]);

    $this->provider->prune(Carbon::createFromDate(2024, 4, 23));
    expect(deferredFailedJobsTable()->count())->toBe(2);

    $this->provider->prune(Carbon::createFromDate(2024, 4, 25));
    expect(deferredFailedJobsTable()->count())->toBe(1);

    $this->provider->prune(Carbon::createFromDate(2024, 4, 30));
    expect(deferredFailedJobsTable()->count())->toBe(0);
});

test('can prune failed jobs with relative hours and minutes', function () {
    Carbon::setTestNow(Carbon::create(2025, 8, 24, 12, 0, 0));

    deferredCreateFailedJobsRecord(['failed_at' => Carbon::create(2025, 8, 24, 11, 45, 0)]);
    deferredCreateFailedJobsRecord(['failed_at' => Carbon::create(2025, 8, 24, 13, 0, 0)]);

    $this->provider->prune(Carbon::create(2025, 8, 24, 11, 45, 0));
    expect(deferredFailedJobsTable()->count())->toBe(2);

    $this->provider->prune(Carbon::create(2025, 8, 24, 14, 0, 0));
    expect(deferredFailedJobsTable()->count())->toBe(0);
});

test('can flush failed jobs', function () {
    Date::setTestNow(Date::now());

    deferredCreateFailedJobsRecord(['failed_at' => Date::now()->subDays(10)]);
    $this->provider->flush();
    expect(deferredFailedJobsTable()->count())->toBe(0);

    deferredCreateFailedJobsRecord(['failed_at' => Date::now()->subDays(10)]);
    $this->provider->flush(15 * 24);
    expect(deferredFailedJobsTable()->count())->toBe(1);

    deferredCreateFailedJobsRecord(['failed_at' => Date::now()->subDays(10)]);
    $this->provider->flush(10 * 24);
    expect(deferredFailedJobsTable()->count())->toBe(0);
});

test('can properly log failed job', function () {
    $uuid = Str::uuid();
    $exception = new Exception(mb_convert_encoding('ÐÑÙ0E\xE2\x�98\xA0World��7B¹!þÿ', 'ISO-8859-1', 'UTF-8'));

    $this->provider->log('database', 'default', json_encode(['uuid' => (string) $uuid]), $exception);

    $exception = (string) mb_convert_encoding($exception, 'UTF-8');

    expect(deferredFailedJobsTable()->count())->toBe(1);
    expect(deferredFailedJobsTable()->first()->exception)->toBe($exception);
});

test('jobs can be counted', function () {
    expect($this->provider->count())->toBe(0);

    $this->provider->log('database', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count())->toBe(1);

    $this->provider->log('database', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('another-connection', 'another-queue', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count())->toBe(3);
});

test('jobs can be counted by connection', function () {
    $this->provider->log('connection-1', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-2', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count('connection-1'))->toBe(1);
    expect($this->provider->count('connection-2'))->toBe(1);

    $this->provider->log('connection-1', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count('connection-1'))->toBe(2);
    expect($this->provider->count('connection-2'))->toBe(1);
});

test('jobs can be counted by queue', function () {
    $this->provider->log('database', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('database', 'queue-2', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count(queue: 'queue-1'))->toBe(1);
    expect($this->provider->count(queue: 'queue-2'))->toBe(1);

    $this->provider->log('database', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    expect($this->provider->count(queue: 'queue-1'))->toBe(2);
    expect($this->provider->count(queue: 'queue-2'))->toBe(1);
});

test('jobs can be counted by queue and connection', function () {
    $this->provider->log('connection-1', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-1', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-2', 'queue-99', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-1', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-2', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);
    $this->provider->log('connection-2', 'queue-1', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException);

    expect($this->provider->count('connection-1', 'queue-99'))->toBe(2);
    expect($this->provider->count('connection-2', 'queue-99'))->toBe(1);
    expect($this->provider->count('connection-1', 'queue-1'))->toBe(1);
    expect($this->provider->count('connection-2', 'queue-1'))->toBe(2);
});
