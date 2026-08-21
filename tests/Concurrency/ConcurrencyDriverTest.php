<?php

use Tests\Concurrency\Fixtures\ExceptionWithParam;
use Voyager\Concurrency\ProcessDriver;
use Voyager\Concurrency\SyncDriver;
use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\Process\Factory as ProcessFactory;
use Voyager\Vessel\Vessel;

/**
 * Build a process factory whose pooled children answer with the given output in order.
 */
function concurrencyFactoryReturning(array $outputs): ProcessFactory
{
    $factory = new ProcessFactory;

    $factory->fake(['*' => $factory->sequence(
        array_map(fn ($output) => $factory->result(output: $output), $outputs)
    )]);

    return $factory;
}

/**
 * Build the JSON payload a successful child process writes to stdout.
 */
function concurrencySuccessful(mixed $result): string
{
    return json_encode(['successful' => true, 'result' => serialize($result)]);
}

beforeEach(function () {
    $this->previousVessel = Vessel::getInstance();

    $vessel = new class extends Vessel
    {
        public function basePath($path = '')
        {
            return __DIR__.($path != '' ? DIRECTORY_SEPARATOR.$path : $path);
        }
    };

    $vessel->singleton(DeferredCallbackCollection::class);

    Vessel::setInstance($vessel);
});

afterEach(function () {
    Vessel::setInstance($this->previousVessel);
});

test('the sync driver runs tasks and preserves keys', function () {
    $results = new SyncDriver()->run([
        'first' => fn () => 1 + 1,
        'second' => fn () => 2 + 2,
    ]);

    expect($results)->toBe(['first' => 2, 'second' => 4]);
});

test('the sync driver preserves callback order', function () {
    $results = new SyncDriver()->run([
        fn () => 'first',
        fn () => 'second',
        fn () => 'third',
    ]);

    expect($results)->toBe(['first', 'second', 'third']);
});

test('the sync driver wraps a single closure', function () {
    expect(new SyncDriver()->run(fn () => 1 + 1))->toBe([2]);
});

test('the sync driver lets exceptions surface', function () {
    new SyncDriver()->run([fn () => throw new Exception('Task failed.')]);
})->throws(Exception::class, 'Task failed.');

test('the sync driver defers tasks until the callback is invoked', function () {
    $ran = false;

    $deferred = new SyncDriver()->defer([function () use (&$ran) {
        $ran = true;
    }]);

    expect($deferred)->toBeInstanceOf(DeferredCallback::class)
        ->and($ran)->toBeFalse();

    $deferred();

    expect($ran)->toBeTrue();
});

test('the process driver maps child results back onto their keys', function () {
    $driver = new ProcessDriver(concurrencyFactoryReturning([
        concurrencySuccessful(2),
        concurrencySuccessful(4),
    ]));

    expect($driver->run([
        'first' => fn () => 1 + 1,
        'second' => fn () => 2 + 2,
    ]))->toBe(['first' => 2, 'second' => 4]);
});

test('the process driver strips trailing gzip output from children', function () {
    $driver = new ProcessDriver(concurrencyFactoryReturning([
        concurrencySuccessful('venusian')."\x1f\x8b".'binary noise',
    ]));

    expect($driver->run([fn () => 'venusian']))->toBe(['venusian']);
});

test('the process driver throws when a child process fails', function () {
    $factory = new ProcessFactory;
    $factory->fake(['*' => $factory->result(errorOutput: 'Segmentation fault', exitCode: 1)]);

    new ProcessDriver($factory)->run([fn () => 1 + 1]);
})->throws(Exception::class, 'Concurrent process failed with exit code [1]. Message: Segmentation fault');

test('the process driver rethrows a child exception from its message', function () {
    $driver = new ProcessDriver(concurrencyFactoryReturning([
        json_encode([
            'successful' => false,
            'exception' => Exception::class,
            'message' => 'This is a different exception',
            'parameters' => [],
        ]),
    ]));

    $driver->run([fn () => 1 + 1]);
})->throws(Exception::class, 'This is a different exception');

test('the process driver rethrows a child exception with its constructor parameters', function () {
    $driver = new ProcessDriver(concurrencyFactoryReturning([
        json_encode([
            'successful' => false,
            'exception' => ExceptionWithParam::class,
            'message' => 'ignored in favour of the parameters',
            'parameters' => [
                'uri' => 'https://api.example.com',
                'statusCode' => 400,
                'reason' => 'Bad Request',
                'responseBody' => 'Invalid payload',
            ],
        ]),
    ]));

    $driver->run([fn () => 1 + 1]);
})->throws(ExceptionWithParam::class, 'API request to https://api.example.com failed with status 400 Bad Request');

test('the process driver defers tasks until the callback is invoked', function () {
    $factory = new ProcessFactory;
    $factory->fake();

    $deferred = new ProcessDriver($factory)->defer([fn () => 1 + 1]);

    expect($deferred)->toBeInstanceOf(DeferredCallback::class);
    $factory->assertNothingRan();

    $deferred();

    $factory->assertRanTimes(fn () => true, 1);
});
