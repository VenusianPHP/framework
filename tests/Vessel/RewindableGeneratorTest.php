<?php

use Voyager\Vessel\RewindableGenerator;

test('count uses the provided value', function () {
    $generator = new RewindableGenerator(function () {
        yield 'foo';
    }, 999);

    expect($generator)->toHaveCount(999);
});

test('the count callback is resolved lazily and only once', function () {
    $called = 0;

    $generator = new RewindableGenerator(function () {
        yield 'foo';
    }, function () use (&$called) {
        $called++;

        return 500;
    });

    expect($called)->toBe(0);

    expect($generator)->toHaveCount(500);

    count($generator);

    expect($called)->toBe(1);
});
