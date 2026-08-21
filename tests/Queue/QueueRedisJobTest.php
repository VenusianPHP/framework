<?php

use Voyager\Vessel\Vessel;
use Voyager\Queue\Jobs\RedisJob;
use Voyager\Queue\RedisQueue;
use Mockery as m;

function queueRedisJob()
{
    return new RedisJob(
        m::mock(Vessel::class),
        m::mock(RedisQueue::class),
        json_encode(['job' => 'foo', 'data' => ['data'], 'attempts' => 1]),
        json_encode(['job' => 'foo', 'data' => ['data'], 'attempts' => 2]),
        'connection-name',
        'default'
    );
}

test('fire properly calls the job handler', function () {
    $job = queueRedisJob();
    $job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler = m::mock(stdClass::class));
    $handler->shouldReceive('fire')->once()->with($job, ['data']);

    $job->fire();
});

test('delete removes the job from redis', function () {
    $job = queueRedisJob();
    $job->getRedisQueue()->shouldReceive('deleteReserved')->once()
        ->with('default', $job);

    $job->delete();
});

test('release properly releases job onto redis', function () {
    $job = queueRedisJob();
    $job->getRedisQueue()->shouldReceive('deleteAndRelease')->once()
        ->with('default', $job, 1);

    $job->release(1);
});
