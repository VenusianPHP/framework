<?php

use Tests\Bus\Fixtures\ConnectionEnum;
use Tests\Bus\Fixtures\FakeJob;
use Tests\Bus\Fixtures\QueueEnum;

test('on connection', function (mixed $connection, ?string $expected) {
    $job = new FakeJob();
    $job->onConnection($connection);

    expect($job->connection)->toBe($expected);
})->with([
    'uses string' => ['redis', 'redis'],
    'uses BackedEnum #1' => [ConnectionEnum::SQS, 'sqs'],
    'uses BackedEnum #2' => [ConnectionEnum::REDIS, 'redis'],
    'uses null' => [null, null],
]);

test('all on connection', function (mixed $connection, ?string $expected) {
    $job = new FakeJob();
    $job->allOnConnection($connection);

    expect($job->connection)->toBe($expected)
        ->and($job->chainConnection)->toBe($expected);
})->with([
    'uses string' => ['redis', 'redis'],
    'uses BackedEnum #1' => [ConnectionEnum::SQS, 'sqs'],
    'uses BackedEnum #2' => [ConnectionEnum::REDIS, 'redis'],
    'uses null' => [null, null],
]);

test('on queue', function (mixed $queue, ?string $expected) {
    $job = new FakeJob();
    $job->onQueue($queue);

    expect($job->queue)->toBe($expected);
})->with([
    'uses string' => ['high', 'high'],
    'uses BackedEnum #1' => [QueueEnum::DEFAULT, 'default'],
    'uses BackedEnum #2' => [QueueEnum::HIGH, 'high'],
    'uses null' => [null, null],
]);

test('all on queue', function (mixed $queue, ?string $expected) {
    $job = new FakeJob();
    $job->allOnQueue($queue);

    expect($job->queue)->toBe($expected)
        ->and($job->chainQueue)->toBe($expected);
})->with([
    'uses string' => ['high', 'high'],
    'uses BackedEnum #1' => [QueueEnum::DEFAULT, 'default'],
    'uses BackedEnum #2' => [QueueEnum::HIGH, 'high'],
    'uses null' => [null, null],
]);
