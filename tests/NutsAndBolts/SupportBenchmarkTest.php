<?php

use Voyager\NutsAndBolts\Benchmark;

test('measure returns milliseconds for a single callback and an array for many', function () {
    expect(Benchmark::measure(fn () => 1 + 1))->toBeNumeric()
        ->and(Benchmark::measure([
            'first' => fn () => 1 + 1,
            'second' => fn () => 2 + 2,
        ], 3))->toBeArray();
});

test('value returns the result alongside the duration', function () {
    expect(Benchmark::value(fn () => 1 + 1))->toBeArray();
});

test('Benchmark is macroable', function () {
    $macroName = 'testMacroable';

    expect(Benchmark::hasMacro($macroName))->toBeFalse();

    // Register a macro to test
    Benchmark::macro($macroName, fn () => true);

    expect(Benchmark::hasMacro($macroName))->toBeTrue()
        ->and(Benchmark::{$macroName}())->toBeTrue();
});
