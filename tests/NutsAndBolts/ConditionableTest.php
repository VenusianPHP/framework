<?php

use Voyager\NutsAndBolts\Collection;

test('when applies the callback for truthy values', function () {
    $result = collect(['a'])->when(true, fn (Collection $collection) => $collection->push('b'));

    expect($result->all())->toBe(['a', 'b']);
});

test('unless skips the callback for truthy values', function () {
    $result = collect(['a'])->unless(true, fn (Collection $collection) => $collection->push('b'));

    expect($result->all())->toBe(['a']);
});

test('when runs the default branch for falsy values', function () {
    $result = collect(['a'])->when(
        false,
        fn (Collection $collection) => $collection->push('b'),
        fn (Collection $collection) => $collection->push('c'),
    );

    expect($result->all())->toBe(['a', 'c']);
});

test('the condition may be a closure', function () {
    $result = collect([1, 2, 3])->when(fn (Collection $c) => $c->count() > 2, fn (Collection $c) => $c->take(1));

    expect($result->all())->toBe([1]);
});

test('whenEmpty and whenNotEmpty branch on contents', function () {
    expect(collect([])->whenEmpty(fn () => collect(['filled']))->all())->toBe(['filled'])
        ->and(collect(['a'])->whenNotEmpty(fn (Collection $c) => $c->push('b'))->all())->toBe(['a', 'b']);
});
