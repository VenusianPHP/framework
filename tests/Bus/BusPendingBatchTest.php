<?php

use Tests\Bus\Fixtures\BatchableJob;
use Voyager\Bus\Batch;
use Voyager\Bus\Batchable;
use Voyager\Bus\BatchRepository;
use Voyager\Bus\PendingBatch;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\NutsAndBolts\Collection;
use Voyager\Vessel\Vessel;

test('pending batch may be configured and dispatched', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $pendingBatch = $pendingBatch->before(function () {
        //
    })->progress(function () {
        //
    })->then(function () {
        //
    })->catch(function () {
        //
    })->allowFailures()->onConnection('test-connection')->onQueue('test-queue')->withOption('extra-option', 123);

    expect($pendingBatch->connection())->toBe('test-connection')
        ->and($pendingBatch->queue())->toBe('test-queue')
        ->and($pendingBatch->beforeCallbacks())->toHaveCount(1)
        ->and($pendingBatch->progressCallbacks())->toHaveCount(1)
        ->and($pendingBatch->thenCallbacks())->toHaveCount(1)
        ->and($pendingBatch->catchCallbacks())->toHaveCount(1)
        ->and($pendingBatch->options)->toHaveKey('extra-option')
        ->and($pendingBatch->options['extra-option'])->toBe(123);

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = Mockery::mock(stdClass::class));
    $batch->shouldReceive('add')->once()->with(Mockery::type(Collection::class))->andReturn($batch = Mockery::mock(Batch::class));

    $vessel->instance(BatchRepository::class, $repository);

    $pendingBatch->dispatch();
});

test('batch is deleted from storage if exception thrown during batching', function () {
    $vessel = new Vessel;

    $job = new class {
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $repository = Mockery::mock(BatchRepository::class);

    $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = Mockery::mock(stdClass::class));

    $batch->id = 'test-id';

    $batch->shouldReceive('add')->once()->andReturnUsing(function () {
        throw new RuntimeException('Failed to add jobs...');
    });

    $repository->shouldReceive('delete')->once()->with('test-id');

    $vessel->instance(BatchRepository::class, $repository);

    $pendingBatch->dispatch();
})->throws(RuntimeException::class);

test('batch is dispatched when dispatchIf is true', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldReceive('dispatch')->once();
    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('store')->once()->andReturn($batch = Mockery::mock(stdClass::class));
    $batch->shouldReceive('add')->once()->andReturn($batch = Mockery::mock(Batch::class));

    $vessel->instance(BatchRepository::class, $repository);

    $result = $pendingBatch->dispatchIf(true);

    expect($result)->toBeInstanceOf(Batch::class);
});

test('batch is not dispatched when dispatchIf is false', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldNotReceive('dispatch');
    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $repository = Mockery::mock(BatchRepository::class);
    $vessel->instance(BatchRepository::class, $repository);

    $result = $pendingBatch->dispatchIf(false);

    expect($result)->toBeNull();
});

test('batch is dispatched when dispatchUnless is false', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldReceive('dispatch')->once();
    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('store')->once()->andReturn($batch = Mockery::mock(stdClass::class));
    $batch->shouldReceive('add')->once()->andReturn($batch = Mockery::mock(Batch::class));

    $vessel->instance(BatchRepository::class, $repository);

    $result = $pendingBatch->dispatchUnless(false);

    expect($result)->toBeInstanceOf(Batch::class);
});

test('batch is not dispatched when dispatchUnless is true', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldNotReceive('dispatch');
    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $repository = Mockery::mock(BatchRepository::class);
    $vessel->instance(BatchRepository::class, $repository);

    $result = $pendingBatch->dispatchUnless(true);

    expect($result)->toBeNull();
});

test('batch before event is called', function () {
    $vessel = new Vessel;

    $eventDispatcher = Mockery::mock(Dispatcher::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $vessel->instance(Dispatcher::class, $eventDispatcher);

    $job = new class
    {
        use Batchable;
    };

    $beforeCalled = false;

    $pendingBatch = new PendingBatch($vessel, new Collection([$job]));

    $pendingBatch = $pendingBatch->before(function () use (&$beforeCalled) {
        $beforeCalled = true;
    })->onConnection('test-connection')->onQueue('test-queue');

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = Mockery::mock(stdClass::class));
    $batch->shouldReceive('add')->once()->with(Mockery::type(Collection::class))->andReturn($batch = Mockery::mock(Batch::class));

    $vessel->instance(BatchRepository::class, $repository);

    $pendingBatch->dispatch();

    expect($beforeCalled)->toBeTrue();
});

test('it throws exception if batched job is not batchable', function () {
    $nonBatchableJob = new class {
    };

    new PendingBatch(new Vessel, new Collection([$nonBatchableJob]));
})->throws(RuntimeException::class);

test('it throws an exception if batched job contains batch with nonbatchable job', function () {
    $vessel = new Vessel;
    new PendingBatch(
        $vessel,
        new Collection(
            [new PendingBatch($vessel, new Collection([new BatchableJob, new class {
            }]))]
        )
    );
})->throws(RuntimeException::class);

test('it can batch a closure', function () {
    new PendingBatch(
        new Vessel,
        new Collection([
            function () {
            },
        ])
    );
    $this->expectNotToPerformAssertions();
});

test('allow failures with boolean true enables failure tolerance', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures(true);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toBeEmpty();
});

test('allow failures with boolean false disables failure tolerance', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures(false);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeFalse()
        ->and($batch->failureCallbacks())->toBeEmpty();
});

test('allow failures with single closure registers callback', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures(static fn (): true => true);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toHaveCount(1);
});

test('allow failures with single callable registers callback', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures('strlen');

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toHaveCount(1);
});

test('allow failures with array of callables registers multiple callbacks', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures([
        static fn (): true => true,
        'strlen',
        [$batch, 'failureCallbacks'],
        strlen(...),
    ]);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toHaveCount(4);
});

test('allow failures registers only valid callbacks', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures([
        // 3 valid
        static fn (): true => true,
        'strlen',
        [$batch, 'failureCallbacks'],
        // 5 invalid
        'invalid_function_name',
        123,
        null,
        [],
        new stdClass,
    ]);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toHaveCount(3);
});

test('allow failures with empty array enables tolerance without callbacks', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $result = $batch->allowFailures([]);

    expect($result)->toBe($batch)
        ->and($batch->options['allowFailures'])->toBeTrue()
        ->and($batch->failureCallbacks())->toBeEmpty();
});

test('allow failures is chainable', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    expect($batch->allowFailures(true))->toBe($batch)
        ->and($batch->allowFailures(false))->toBe($batch)
        ->and($batch->allowFailures(static fn (): true => true))->toBe($batch)
        ->and($batch->allowFailures('strlen'))->toBe($batch)
        ->and($batch->allowFailures([static fn (): true => true, 'strlen']))->toBe($batch)
        ->and($batch->allowFailures([]))->toBe($batch);
});

test('failure callbacks accessor returns registered callbacks', function () {
    $batch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    expect($batch->failureCallbacks())->toBeEmpty();

    $batch->allowFailures(
        static fn (): true => true
    );

    expect($batch->failureCallbacks())->toHaveCount(1);

    $freshBatch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $freshBatch->allowFailures([
        'strlen',
        [$freshBatch, 'failureCallbacks'],
    ]);

    expect($freshBatch->failureCallbacks())->toHaveCount(2);

    $anotherBatch = new PendingBatch(new Vessel, new Collection([new BatchableJob]));

    $anotherBatch->allowFailures([
        static fn (): false => false,
        'trim',
        123,
        'invalid_function',
    ]);

    expect($anotherBatch->failureCallbacks())->toHaveCount(2);
});
