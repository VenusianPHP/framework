<?php

use Voyager\Queue\Jobs\RedisJob;
use Voyager\Queue\MaxAttemptsExceededException;
use Voyager\Queue\TimeoutExceededException;

test('it can create timeout exception for job', function () {
    $e = TimeoutExceededException::forJob($job = new MyFakeRedisJob());

    expect($e->getMessage())->toBe('App\\Jobs\\UnderlyingJob has timed out.')
        ->and($e->job)->toBe($job);
});

test('it can create max attempts exception for job', function () {
    $e = MaxAttemptsExceededException::forJob($job = new MyFakeRedisJob());

    expect($e->getMessage())->toBe('App\\Jobs\\UnderlyingJob has been attempted too many times.')
        ->and($e->job)->toBe($job);
});

class MyFakeRedisJob extends RedisJob
{
    public function __construct()
    {
        //
    }

    public function resolveName()
    {
        return 'App\\Jobs\\UnderlyingJob';
    }
}
