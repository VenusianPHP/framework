<?php

use Voyager\Bus\Batch;
use Voyager\Bus\BatchRepository;
use Voyager\Bus\PendingBatch;
use Voyager\Bus\Signals\BatchDispatched;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\Vessel\ControlPanel;

it('holds a batch dispatched after the response until the deferred callbacks drain', function () {
    $batch = Mockery::mock(Batch::class);
    $batch->shouldReceive('add')->andReturn($batch);

    $repository = Mockery::mock(BatchRepository::class);
    $repository->shouldReceive('store')->andReturn($batch);

    $signals = Mockery::mock(SignalDispatcher::class);
    $signals->shouldReceive('dispatch')->once()->with(Mockery::type(BatchDispatched::class));

    $container = new ControlPanel;
    $container->bind(DeferredCallbackCollection::class, shared: true);
    $container->bind(BatchRepository::class, fn () => $repository);
    $container->bind(SignalDispatcher::class, fn () => $signals);

    $returned = (new PendingBatch($container, new Collection))->dispatchAfterResponse();

    expect($returned)->toBe($batch);

    $container->make(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => $callback->always);
});
