<?php

use Voyager\IOPools\EventLoop;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\PromiseEngines\ReactPromiseEngine;

dataset('defer engines', [
    'guzzle' => [fn () => new GuzzlePromiseEngine],
    'react'  => [fn () => new ReactPromiseEngine],
]);

it('runs the work on the next turn and resolves with its return', function ($engine) {
    $loop = new EventLoop(promise_engine: $engine);
    $ran = false;

    $promise = $loop->defer(function () use (&$ran) { $ran = true; return 5; });

    expect($ran)->toBeFalse()                     // not yet: deferred means deferred
        ->and($promise->wait())->toBe(5)
        ->and($ran)->toBeTrue();
})->with('defer engines');

it('rejects with what the work threw', function () {
    $loop = new EventLoop;

    $promise = $loop->defer(fn () => throw new RuntimeException('deferred boom'));

    expect(fn () => $promise->wait())->toThrow(RuntimeException::class, 'deferred boom');
});

it('runs deferred work in order, all in one turn', function () {
    $loop = new EventLoop;
    $order = [];

    $loop->defer(function () use (&$order) { $order[] = 'a'; });
    $loop->defer(function () use (&$order) { $order[] = 'b'; });
    $last = $loop->defer(function () use (&$order) { $order[] = 'c'; });

    $last->wait();

    expect($order)->toBe(['a', 'b', 'c']);
});

it('does not keep run() alive once the queue is drained', function () {
    $loop = new EventLoop;
    $loop->defer(fn () => 1);

    $start = microtime(true);
    $loop->run();

    expect(microtime(true) - $start)->toBeLessThan(0.1);
});

it('work deferred from deferred work runs on a later turn, not the same one', function () {
    $loop = new EventLoop;
    $turns = [];
    $turn = 0;
    $loop->every(0.001, function () use (&$turn) { $turn++; }, 'clock');

    $inner = null;
    $loop->defer(function () use ($loop, &$inner, &$turns, &$turn) {
        $turns['outer'] = $turn;
        $inner = $loop->defer(function () use (&$turns, &$turn) { $turns['inner'] = $turn; });
    });

    // arrow functions capture by value, so this has to be a closure: $inner is assigned later
    $loop->until(function () use (&$inner) {
        return ! is_null($inner) && $inner->settled();
    });

    expect($turns['inner'])->toBeGreaterThan($turns['outer']);
});

it('suspends instead of borrowing when waited on under async()', function () {
    $loop = new EventLoop;
    $steps = [];

    $task = $loop->async(function () use ($loop, &$steps) {
        $steps[] = 'before';
        $v = $loop->defer(fn () => 'deferred')->wait();
        $steps[] = $v;
        return $v;
    });
    $steps[] = 'async returned';           // the fiber is parked on its wait here

    expect($task->wait())->toBe('deferred')
        ->and($steps)->toBe(['before', 'async returned', 'deferred']);
});
