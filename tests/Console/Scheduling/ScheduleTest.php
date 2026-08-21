<?php

declare(strict_types=1);

use Tests\Console\Fixtures\JobToTestWithSchedule;
use Tests\Console\Fixtures\JobWithDisplayName;
use Voyager\Console\Scheduling\EventMutex;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Console\Scheduling\SchedulingMutex;
use Voyager\Vessel\Vessel;

covers(Schedule::class);

/** A fresh container carrying both scheduling mutexes, installed as the global one. */
function scheduleVessel(): Vessel
{
    $vessel = new Vessel;
    Vessel::setInstance($vessel);

    $vessel->instance(EventMutex::class, Mockery::mock(EventMutex::class));
    $vessel->instance(SchedulingMutex::class, Mockery::mock(SchedulingMutex::class));

    return $vessel;
}

test('a scheduled job honours displayName when the method exists', function (object $job, string $jobName) {
    $vessel = scheduleVessel();

    $scheduledJob = (new Schedule)->job($job);

    expect($scheduledJob->description)->toBe($jobName)
        ->and($vessel->resolved(JobToTestWithSchedule::class))->toBeFalse();
})->with([
    'without displayName' => [new JobToTestWithSchedule, JobToTestWithSchedule::class],
    'with displayName' => [new JobWithDisplayName, 'testJob-123'],
]);

test('a job supplied as a class name is not instantiated', function () {
    $vessel = scheduleVessel();

    $scheduledJob = (new Schedule)->job(JobToTestWithSchedule::class);

    expect($scheduledJob->description)->toBe(JobToTestWithSchedule::class)
        ->and($vessel->resolved(JobToTestWithSchedule::class))->toBeFalse();
});
