<?php

use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Runtimes\FiberRuntime;
use Voyager\Workflows\Runtimes\SyncRuntime;

test('async returns an awaitable that resolves to the work result', function (string $runtime) {
    /** @var AsyncRuntime $runtime */
    $runtime = new $runtime;

    $awaitable = $runtime->async(fn () => 'done');

    expect($awaitable)->toBeInstanceOf(Awaitable::class)
        ->and($runtime->await($awaitable))->toBe('done');
})->with('async runtimes');

test('await passes plain values straight through', function (string $runtime) {
    $runtime = new $runtime;

    expect($runtime->await('plain'))->toBe('plain')
        ->and($runtime->await(null))->toBeNull();
})->with('async runtimes');

test('resolve lifts a plain value and leaves awaitables alone', function (string $runtime) {
    $runtime = new $runtime;

    $lifted = $runtime->resolve(42);
    $existing = $runtime->async(fn () => 7);

    expect($runtime->await($lifted))->toBe(42)
        ->and($runtime->resolve($existing))->toBe($existing);
})->with('async runtimes');

test('a throw inside async surfaces when awaited', function (string $runtime) {
    $runtime = new $runtime;

    $awaitable = $runtime->async(function () {
        throw new RuntimeException('boom');
    });

    expect(fn () => $runtime->await($awaitable))
        ->toThrow(RuntimeException::class, 'boom');
})->with('async runtimes');

test('an awaitable resolves to the same value every time it is awaited', function (string $runtime) {
    $runtime = new $runtime;

    $calls = 0;
    $awaitable = $runtime->async(function () use (&$calls) {
        $calls++;

        return 'once';
    });

    expect($runtime->await($awaitable))->toBe('once')
        ->and($runtime->await($awaitable))->toBe('once')
        ->and($calls)->toBe(1);
})->with('async runtimes');

test('all preserves keys', function (string $runtime) {
    $runtime = new $runtime;

    $results = $runtime->await($runtime->all([
        'first' => fn () => 'a',
        'second' => fn () => 'b',
    ]));

    expect($results)->toBe(['first' => 'a', 'second' => 'b']);
})->with('async runtimes');

test('all accepts awaitables alongside closures', function (string $runtime) {
    $runtime = new $runtime;

    $results = $runtime->await($runtime->all([
        $runtime->async(fn () => 1),
        fn () => 2,
    ]));

    expect($results)->toBe([1, 2]);
})->with('async runtimes');

test('all lets every entry settle before reporting the first failure', function (string $runtime) {
    $runtime = new $runtime;

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

test('all on an empty set resolves to an empty array', function (string $runtime) {
    $runtime = new $runtime;

    expect($runtime->await($runtime->all([])))->toBe([]);
})->with('async runtimes');

test('delay waits the requested time', function (string $runtime) {
    $runtime = new $runtime;

    $started = microtime(true);
    $runtime->await($runtime->delay(0.02));

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.018);
})->with('async runtimes');

test('a runtime refuses awaitables belonging to another runtime', function () {
    $sync = new SyncRuntime;
    $fiber = new FiberRuntime;

    expect(fn () => $sync->await($fiber->async(fn () => 'x')))
        ->toThrow(WorkflowRuntimeException::class)
        ->and(fn () => $fiber->await($sync->async(fn () => 'x')))
        ->toThrow(WorkflowRuntimeException::class);
});

test('overlapping runtimes run delayed work concurrently', function (string $runtime) {
    $runtime = new $runtime;

    $started = microtime(true);

    $runtime->await($runtime->all([
        fn () => $runtime->await($runtime->delay(0.03)),
        fn () => $runtime->await($runtime->delay(0.03)),
        fn () => $runtime->await($runtime->delay(0.03)),
    ]));

    expect(microtime(true) - $started)->toBeLessThan(0.075);
})->with('overlapping async runtimes');

test('the concurrency limit caps how much runs at once', function (string $runtime) {
    $runtime = new $runtime;

    $started = microtime(true);

    $runtime->await($runtime->all([
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
        fn () => $runtime->await($runtime->delay(0.02)),
    ], concurrency: 2));

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.038);
})->with('overlapping async runtimes');

test('then chains without blocking', function (string $runtime) {
    $runtime = new $runtime;

    $chained = $runtime->async(fn () => 2)->then(fn (int $value) => $value * 5);

    expect($runtime->await($chained))->toBe(10);
})->with('async runtimes');

test('then can recover from a rejection', function (string $runtime) {
    $runtime = new $runtime;

    $recovered = $runtime->async(function () {
        throw new RuntimeException('nope');
    })->then(null, fn (Throwable $e) => 'recovered: '.$e->getMessage());

    expect($runtime->await($recovered))->toBe('recovered: nope');
})->with('async runtimes');
