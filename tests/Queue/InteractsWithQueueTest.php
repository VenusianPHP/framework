<?php

use Voyager\Contracts\Queue\Job;
use Voyager\Queue\InteractsWithQueue;
use Mockery as m;

test('creates an exception from string', function () {
    $queueJob = m::mock(Job::class);
    $queueJob->shouldReceive('fail')->withArgs(function ($e) {
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertEquals('Whoops!', $e->getMessage());

        return true;
    });

    $job = new class
    {
        use InteractsWithQueue;

        public $job;
    };

    $job->job = $queueJob;
    $job->fail('Whoops!');
});
