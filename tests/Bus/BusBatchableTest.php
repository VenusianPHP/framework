<?php

use Voyager\Bus\Batchable;
use Voyager\Bus\BatchRepository;
use Voyager\Testing\Fakes\BatchFake;
use Voyager\Vessel\Vessel;

test('batch may be retrieved', function () {
    $class = new class
    {
        use Batchable;
    };

    expect($class->withBatchId('test-batch-id'))->toBe($class)
        ->and($class->batchId)->toBe('test-batch-id');

    Vessel::setInstance($vessel = new Vessel);

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('find')->once()->with('test-batch-id')->andReturn('test-batch');
    $vessel->instance(BatchRepository::class, $repository);

    expect($class->batch())->toBe('test-batch');

    Vessel::setInstance(null);
});

test('with fake batch sets and returns fake', function () {
    $job = new class
    {
        use Batchable;
    };

    [$self, $batch] = $job->withFakeBatch('test-batch-id', 'test-batch-name', 3, 3, 0, [], []);

    expect($self)->toBe($job)
        ->and($batch)->toBeInstanceOf(BatchFake::class)
        ->and($job->batch())->toBe($batch)
        ->and($job->batch()->id)->toBe('test-batch-id')
        ->and($job->batch()->name)->toBe('test-batch-name')
        ->and($job->batch()->totalJobs)->toBe(3);
});

test('batching reflects cancelled state', function () {
    $job = new class
    {
        use Batchable;
    };

    $job->withFakeBatch('test-batch-id', 'test-batch-name');

    expect($job->batching())->toBeTrue();

    $job->batch()->cancel();

    expect($job->batching())->toBeFalse();
});
