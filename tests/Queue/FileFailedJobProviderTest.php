<?php

use Voyager\Queue\Failed\FileFailedJobProvider;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;

function logFailedJob($connection = 'connection', $queue = 'queue')
{
    $uuid = Str::uuid();

    $exception = new Exception("Something went wrong at job [{$uuid}].");

    test()->provider->log($connection, $queue, json_encode(['uuid' => (string) $uuid]), $exception);

    return [(string) $uuid, $exception];
}

beforeEach(function () {
    $this->path = @tempnam('tmp', 'file_failed_job_provider_test');
    $this->provider = new FileFailedJobProvider($this->path);
});

test('can log failed jobs', function () {
    [$uuid, $exception] = logFailedJob();

    $failedJobs = $this->provider->all();

    $this->assertEquals([
        (object) [
            'id' => $uuid,
            'connection' => 'connection',
            'queue' => 'queue',
            'payload' => json_encode(['uuid' => $uuid]),
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => $failedJobs[0]->failed_at,
            'failed_at_timestamp' => $failedJobs[0]->failed_at_timestamp,
        ],
    ], $failedJobs);
});

test('can retrieve all failed jobs', function () {
    try {
        Carbon::setTestNow(now());

        [$uuidOne, $exceptionOne] = logFailedJob();
        [$uuidTwo, $exceptionTwo] = logFailedJob();

        $failedJobs = $this->provider->all();

        $this->assertEquals([
            (object) [
                'id' => $uuidTwo,
                'connection' => 'connection',
                'queue' => 'queue',
                'payload' => json_encode(['uuid' => $uuidTwo]),
                'exception' => (string) mb_convert_encoding($exceptionTwo, 'UTF-8'),
                'failed_at' => $failedJobs[1]->failed_at,
                'failed_at_timestamp' => $failedJobs[1]->failed_at_timestamp,
            ],
            (object) [
                'id' => $uuidOne,
                'connection' => 'connection',
                'queue' => 'queue',
                'payload' => json_encode(['uuid' => $uuidOne]),
                'exception' => (string) mb_convert_encoding($exceptionOne, 'UTF-8'),
                'failed_at' => $failedJobs[0]->failed_at,
                'failed_at_timestamp' => $failedJobs[0]->failed_at_timestamp,
            ],
        ], $failedJobs);
    } finally {
        Carbon::setTestNow();
    }
});

test('can find failed jobs', function () {
    [$uuid, $exception] = logFailedJob();

    $failedJob = $this->provider->find($uuid);

    $this->assertEquals((object) [
        'id' => $uuid,
        'connection' => 'connection',
        'queue' => 'queue',
        'payload' => json_encode(['uuid' => (string) $uuid]),
        'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
        'failed_at' => $failedJob->failed_at,
        'failed_at_timestamp' => $failedJob->failed_at_timestamp,
    ], $failedJob);
});

test('null is returned if job not found', function () {
    $uuid = Str::uuid();

    $failedJob = $this->provider->find($uuid);

    expect($failedJob)->toBeNull();
});

test('can forget failed jobs', function () {
    [$uuid] = logFailedJob();

    $this->provider->forget($uuid);

    $failedJob = $this->provider->find($uuid);

    expect($failedJob)->toBeNull();
});

test('can flush failed jobs', function () {
    logFailedJob();
    logFailedJob();

    $this->provider->flush();

    $failedJobs = $this->provider->all();

    expect($failedJobs)->toBeEmpty();
});

test('can prune failed jobs', function () {
    logFailedJob();
    logFailedJob();

    $this->provider->prune(now()->addDay(1));
    $failedJobs = $this->provider->all();
    expect($failedJobs)->toBeEmpty();

    logFailedJob();
    logFailedJob();

    $this->provider->prune(now()->subDay(1));
    $failedJobs = $this->provider->all();
    expect($failedJobs)->toHaveCount(2);
});

test('can prune failed jobs with relative hours', function () {
    logFailedJob();
    logFailedJob();

    $this->provider->prune(now()->addHour(1));
    $failedJobs = $this->provider->all();
    expect($failedJobs)->toBeEmpty();

    logFailedJob();
    logFailedJob();

    $this->provider->prune(now()->subHour(1));
    $failedJobs = $this->provider->all();
    expect($failedJobs)->toHaveCount(2);
});

test('empty failed jobs by default', function () {
    $failedJobs = $this->provider->all();

    expect($failedJobs)->toBeEmpty();
});

test('jobs can be counted', function () {
    expect($this->provider->count())->toBe(0);

    logFailedJob('database', 'default');
    expect($this->provider->count())->toBe(1);

    logFailedJob('database', 'default');
    logFailedJob('another-connection', 'another-queue');
    expect($this->provider->count())->toBe(3);
});

test('jobs can be counted by connection', function () {
    logFailedJob('connection-1', 'default');
    logFailedJob('connection-2', 'default');
    expect($this->provider->count('connection-1'))->toBe(1);
    expect($this->provider->count('connection-2'))->toBe(1);

    logFailedJob('connection-1', 'default');
    expect($this->provider->count('connection-1'))->toBe(2);
    expect($this->provider->count('connection-2'))->toBe(1);
});

test('jobs can be counted by queue', function () {
    logFailedJob('database', 'queue-1');
    logFailedJob('database', 'queue-2');
    expect($this->provider->count(queue: 'queue-1'))->toBe(1);
    expect($this->provider->count(queue: 'queue-2'))->toBe(1);

    logFailedJob('database', 'queue-1');
    expect($this->provider->count(queue: 'queue-1'))->toBe(2);
    expect($this->provider->count(queue: 'queue-2'))->toBe(1);
});

test('jobs can be counted by queue and connection', function () {
    logFailedJob('connection-1', 'queue-99');
    logFailedJob('connection-1', 'queue-99');
    logFailedJob('connection-2', 'queue-99');
    logFailedJob('connection-1', 'queue-1');
    logFailedJob('connection-2', 'queue-1');
    logFailedJob('connection-2', 'queue-1');
    expect($this->provider->count('connection-1', 'queue-99'))->toBe(2);
    expect($this->provider->count('connection-2', 'queue-99'))->toBe(1);
    expect($this->provider->count('connection-1', 'queue-1'))->toBe(1);
    expect($this->provider->count('connection-2', 'queue-1'))->toBe(2);
});
