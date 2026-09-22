<?php

use Voyager\Contracts\IOPools\Promise;
use Voyager\IOPools\EventLoop;
use Voyager\Workflows\Runtimes\LoopRuntime;

test('async returns an awaitable that resolves to the work result', function (LoopRuntime $runtime) {
    $awaitable = $runtime->async(fn () => 'done');

    expect($awaitable)->toBeInstanceOf(Promise::class)
        ->and($runtime->await($awaitable))->toBe('done');
})->with('async runtimes');

test('await passes plain values straight through', function (LoopRuntime $runtime) {
    expect($runtime->await('plain'))->toBe('plain')
        ->and($runtime->await(null))->toBeNull();
})->with('async runtimes');

test('resolve lifts a plain value and leaves awaitables alone', function (LoopRuntime $runtime) {
    $lifted = $runtime->resolve(42);
    $existing = $runtime->async(fn () => 7);

    expect($runtime->await($lifted))->toBe(42)
        ->and($runtime->resolve($existing))->toBe($existing);
})->with('async runtimes');

test('a throw inside async surfaces when awaited', function (LoopRuntime $runtime) {
    $awaitable = $runtime->async(function () {
        throw new RuntimeException('boom');
    });

    expect(fn () => $runtime->await($awaitable))
        ->toThrow(RuntimeException::class, 'boom');
})->with('async runtimes');

test('an awaitable resolves to the same value every time it is awaited', function (LoopRuntime $runtime) {
    $calls = 0;
    $awaitable = $runtime->async(function () use (&$calls) {
        $calls++;

        return 'once';
    });

    expect($runtime->await($awaitable))->toBe('once')
        ->and($runtime->await($awaitable))->toBe('once')
        ->and($calls)->toBe(1);
})->with('async runtimes');

test('all preserves keys', function (LoopRuntime $runtime) {
    $results = $runtime->await($runtime->all([
        'first' => fn () => 'a',
        'second' => fn () => 'b',
    ]));

    expect($results)->toBe(['first' => 'a', 'second' => 'b']);
})->with('async runtimes');

test('all accepts awaitables alongside closures', function (LoopRuntime $runtime) {
    $results = $runtime->await($runtime->all([
        $runtime->async(fn () => 1),
        fn () => 2,
    ]));

    expect($results)->toBe([1, 2]);
})->with('async runtimes');

test('all lets every entry settle before reporting the first failure', function (LoopRuntime $runtime) {
    $finished = [];

    $awaitable = $runtime->all([
        'ok' => function () use (&$finished) {
            $finished[] = 'ok';

            return 'fine';
        },
        'bad' => function () {
            throw new RuntimeException('second failed');
        },
        'worse' => function () {
            throw new RuntimeException('third failed');
        },
        'late' => function () use (&$finished) {
            $finished[] = 'late';

            return 'fine';
        },
    ]);

    expect(fn () => $runtime->await($awaitable))
        ->toThrow(RuntimeException::class, 'second failed')
        ->and($finished)->toBe(['ok', 'late']);
})->with('async runtimes');

test('all on an empty set resolves to an empty array', function (LoopRuntime $runtime) {
    expect($runtime->await($runtime->all([])))->toBe([]);
})->with('async runtimes');

test('delay waits the requested time', function (LoopRuntime $runtime) {
    $started = microtime(true);
    $runtime->await($runtime->delay(0.02));

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.018);
})->with('async runtimes');

test('overlapping runtimes run delayed work concurrently', function (LoopRuntime $runtime) {
    $started = microtime(true);

    $runtime->await($runtime->all([
        fn () => $runtime->await($runtime->delay(0.03)),
        fn () => $runtime->await($runtime->delay(0.03)),
        fn () => $runtime->await($runtime->delay(0.03)),
    ]));

    expect(microtime(true) - $started)->toBeLessThan(0.075);
})->with('overlapping async runtimes');

test('the concurrency limit caps how much runs at once', function (LoopRuntime $runtime) {
    $started = microtime(true);

    $runtime->await($runtime->all([
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
    ], concurrency: 2));

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.038);
})->with('overlapping async runtimes');

test('then chains without blocking', function (LoopRuntime $runtime) {
    $chained = $runtime->async(fn () => 2)->then(fn (int $value) => $value * 5);

    expect($runtime->await($chained))->toBe(10);
})->with('async runtimes');

test('then can recover from a rejection', function (LoopRuntime $runtime) {
    $recovered = $runtime->async(function () {
        throw new RuntimeException('nope');
    })->error(fn (Throwable $e) => 'recovered: '.$e->getMessage());

    expect($runtime->await($recovered))->toBe('recovered: nope');
})->with('async runtimes');

test('all settles every entry before throwing the first failure', function (LoopRuntime $runtime) {
    $ran = [];
    $set = $runtime->all([
        'a' => function () use (&$ran) { $ran[] = 'a'; return 1; },
        'b' => fn () => throw new RuntimeException('b failed'),
        'c' => function () use (&$ran) { $ran[] = 'c'; return 3; },
    ]);

    expect(fn () => $runtime->await($set))->toThrow(RuntimeException::class, 'b failed')
        ->and($ran)->toBe(['a', 'c']);
})->with('async runtimes');

test('await suspends instead of borrowing under the loop\'s own async()', function () {
    $loop = new EventLoop;
    $runtime = new LoopRuntime($loop);
    $steps = [];

    $task = $loop->async(function () use ($runtime, &$steps) {
        $steps[] = 'in';
        $v = $runtime->await($runtime->delay(0.01));
        $steps[] = 'resumed';
        return $v;
    });
    $steps[] = 'after async()';          // the fiber is parked on the timer here, not blocking us

    $task->wait();

    expect($steps)->toBe(['in', 'after async()', 'resumed']);
});
