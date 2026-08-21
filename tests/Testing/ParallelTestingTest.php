<?php

use Voyager\Vessel\Vessel as Container;
use Voyager\Testing\ParallelTesting;

beforeEach(function () {
    Container::setInstance(new Container);

    $_SERVER['LARAVEL_PARALLEL_TESTING'] = 1;
});

afterEach(function () {
    Container::setInstance(null);

    unset($_SERVER['LARAVEL_PARALLEL_TESTING']);
});

test('callbacks', function ($callback) {
    $parallelTesting = new ParallelTesting(Container::getInstance());
    $caller = 'call'.ucfirst($callback).'Callbacks';

    $state = false;
    $parallelTesting->{$caller}($this);
    expect($state)->toBeFalse();

    $parallelTesting->{$callback}(function ($token, $testCase = null) use ($callback, &$state) {
        if (in_array($callback, ['setUpTestCase', 'tearDownTestCase'])) {
            expect($testCase)->toBe($this);
        } else {
            expect($testCase)->toBeNull();
        }

        expect((string) $token)->toBe('1');
        $state = true;
    });

    $parallelTesting->{$caller}($this);
    expect($state)->toBeFalse();

    $parallelTesting->resolveTokenUsing(function () {
        return '1';
    });

    $parallelTesting->{$caller}($this);
    expect($state)->toBeTrue();
})->with([
    'setUpProcess' => ['setUpProcess'],
    'setUpTestCase' => ['setUpTestCase'],
    'setUpTestDatabase' => ['setUpTestDatabase'],
    'setUpTestDatabaseBeforeMigrating' => ['setUpTestDatabaseBeforeMigrating'],
    'tearDownTestCase' => ['tearDownTestCase'],
    'tearDownProcess' => ['tearDownProcess'],
]);

test('options', function () {
    $parallelTesting = new ParallelTesting(Container::getInstance());

    expect($parallelTesting->option('recreate_databases'))->toBeFalse();
    expect($parallelTesting->option('without_databases'))->toBeFalse();

    $parallelTesting->resolveOptionsUsing(function ($option) {
        return $option === 'recreate_databases';
    });

    expect($parallelTesting->option('recreate_caches'))->toBeFalse();
    expect($parallelTesting->option('without_databases'))->toBeFalse();
    expect($parallelTesting->option('recreate_databases'))->toBeTrue();

    $parallelTesting->resolveOptionsUsing(function ($option) {
        return $option === 'without_databases';
    });

    expect($parallelTesting->option('without_databases'))->toBeTrue();
});

test('token', function () {
    $parallelTesting = new ParallelTesting(Container::getInstance());

    expect($parallelTesting->token())->toBeFalse();

    $parallelTesting->resolveTokenUsing(function () {
        return '1';
    });

    expect((string) $parallelTesting->token())->toBe('1');
});
