<?php

use Voyager\Cache\NullStore;

test('items can not be cached', function () {
    $store = new NullStore;
    $store->put('foo', 'bar', 10);

    expect($store->get('foo'))->toBeNull();
});

test('getMultiple returns multiple nulls', function () {
    $store = new NullStore;

    expect($store->many(['foo', 'bar']))->toEqual([
        'foo' => null,
        'bar' => null,
    ]);
});

test('increment and decrement return false', function () {
    $store = new NullStore;

    expect($store->increment('foo'))->toBeFalse()
        ->and($store->decrement('foo'))->toBeFalse();
});
