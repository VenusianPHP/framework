<?php

use Voyager\Bus\Dispatcher;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\Vessel\ControlPanel;

function afterResponseDispatcher(): array
{
    $container = new ControlPanel;
    $container->bind(DeferredCallbackCollection::class, shared: true);

    return [new Dispatcher($container), $container->make(DeferredCallbackCollection::class)];
}

it('holds an after-response command until the deferred callbacks drain', function () {
    [$dispatcher, $deferred] = afterResponseDispatcher();
    $handled = [];

    $dispatcher->dispatchAfterResponse(new stdClass, function ($command) use (&$handled) {
        $handled[] = $command;
    });

    expect($handled)->toBe([]);

    $deferred->invokeWhen(fn ($callback) => $callback->always);

    expect($handled)->toHaveCount(1);
});

it('runs an after-response command even when the process failed', function () {
    [$dispatcher, $deferred] = afterResponseDispatcher();
    $ran = false;

    $dispatcher->dispatchAfterResponse(new stdClass, function () use (&$ran) {
        $ran = true;
    });

    $deferred->invokeWhen(fn ($callback) => $callback->always);

    expect($ran)->toBeTrue();
});
