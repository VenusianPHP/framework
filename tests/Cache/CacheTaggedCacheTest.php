<?php

use Voyager\Cache\ArrayStore;

/** An ArrayStore pre-populated with a few tag groups worth of values. */
function arrayStoreWithTagValues(): ArrayStore
{
    $store = new ArrayStore;

    $tags = ['fruit'];
    $store->tags($tags)->put('a', 'apple', 10);
    $store->tags($tags)->put('b', 'banana', 10);
    $store->tags($tags)->put('c', 'orange', 10);

    $tags = ['fruit', 'color'];
    $store->tags($tags)->putMany([
        'a' => 'red',
        'd' => 'yellow',
        'e' => 'blue',
    ], 10);

    $tags = ['sizes', 'shirt'];
    $store->tags($tags)->putMany([
        'a' => 'small',
        'b' => 'medium',
        'c' => 'large',
    ], 10);

    return $store;
}

test('the cache can be saved with multiple tags', function () {
    $store = new ArrayStore;
    $tags = ['bop', 'zap'];
    $store->tags($tags)->put('foo', 'bar', 10);

    expect($store->tags($tags)->get('foo'))->toBe('bar');
});

test('the cache can be set with a datetime argument', function () {
    $store = new ArrayStore;
    $tags = ['bop', 'zap'];
    $duration = new DateTime;
    $duration->add(new DateInterval('PT10M'));
    $store->tags($tags)->put('foo', 'bar', $duration);

    expect($store->tags($tags)->get('foo'))->toBe('bar');
});

test('the cache saved with multiple tags can be flushed', function () {
    $store = new ArrayStore;
    $tags1 = ['bop', 'zap'];
    $store->tags($tags1)->put('foo', 'bar', 10);
    $tags2 = ['bam', 'pow'];
    $store->tags($tags2)->put('foo', 'bar', 10);
    $store->tags('zap')->flush();

    expect($store->tags($tags1)->get('foo'))->toBeNull()
        ->and($store->tags($tags2)->get('foo'))->toBe('bar');
});

test('tags accept a string argument', function () {
    $store = new ArrayStore;
    $store->tags('bop')->put('foo', 'bar', 10);

    expect($store->tags('bop')->get('foo'))->toBe('bar');
});

test('increment works with tags', function () {
    $store = new ArrayStore;
    $taggableStore = $store->tags('bop');

    $taggableStore->put('foo', 5, 10);

    $value = $taggableStore->increment('foo');
    expect($value)->toBe(6);

    $value = $taggableStore->increment('foo');
    expect($value)->toBe(7);

    $value = $taggableStore->increment('foo', 3);
    expect($value)->toBe(10);

    $value = $taggableStore->increment('foo', -2);
    expect($value)->toBe(8);

    $value = $taggableStore->increment('x');
    expect($value)->toBe(1);

    $value = $taggableStore->increment('y', 10);
    expect($value)->toBe(10);
});

test('decrement works with tags', function () {
    $store = new ArrayStore;
    $taggableStore = $store->tags('bop');

    $taggableStore->put('foo', 50, 10);

    $value = $taggableStore->decrement('foo');
    expect($value)->toBe(49);

    $value = $taggableStore->decrement('foo');
    expect($value)->toBe(48);

    $value = $taggableStore->decrement('foo', 3);
    expect($value)->toBe(45);

    $value = $taggableStore->decrement('foo', -2);
    expect($value)->toBe(47);

    $value = $taggableStore->decrement('x');
    expect($value)->toBe(-1);

    $value = $taggableStore->decrement('y', 10);
    expect($value)->toBe(-10);
});

test('many retrieves several tagged keys at once', function () {
    $store = arrayStoreWithTagValues();

    $values = $store->tags(['fruit'])->many(['a', 'e', 'b', 'd', 'c']);

    expect($values)->toBe([
        'a' => 'apple',
        'e' => null,
        'b' => 'banana',
        'd' => null,
        'c' => 'orange',
    ]);
});

test('many honors default values', function () {
    $store = arrayStoreWithTagValues();

    $values = $store->tags(['fruit'])->many([
        'a' => 147,
        'e' => 547,
        'b' => 'hello world!',
        'x' => 'hello world!',
        'd',
        'c',
    ]);

    expect($values)->toBe([
        'a' => 'apple',
        'e' => 547,
        'b' => 'banana',
        'x' => 'hello world!',
        'd' => null,
        'c' => 'orange',
    ]);
});

test('getMultiple retrieves several tagged keys at once', function () {
    $store = arrayStoreWithTagValues();

    $values = $store->tags(['fruit'])->getMultiple(['a', 'e', 'b', 'd', 'c']);
    expect($values)->toBe([
        'a' => 'apple',
        'e' => null,
        'b' => 'banana',
        'd' => null,
        'c' => 'orange',
    ]);

    $values = $store->tags(['fruit', 'color'])->getMultiple(['a', 'e', 'b', 'd', 'c']);
    expect($values)->toBe([
        'a' => 'red',
        'e' => 'blue',
        'b' => null,
        'd' => 'yellow',
        'c' => null,
    ]);
});

test('getMultiple honors a default value', function () {
    $store = arrayStoreWithTagValues();

    $values = $store->tags(['fruit', 'color'])->getMultiple(['a', 'e', 'b', 'd', 'c'], 547);

    expect($values)->toBe([
        'a' => 'red',
        'e' => 'blue',
        'b' => 547,
        'd' => 'yellow',
        'c' => 547,
    ]);
});

test('tags with increment can be flushed', function () {
    $store = new ArrayStore;
    $store->tags('bop')->increment('foo', 5);
    expect($store->tags('bop')->get('foo'))->toEqual(5);

    $store->tags('bop')->flush();
    expect($store->tags('bop')->get('foo'))->toBeNull();
});

test('tags with decrement can be flushed', function () {
    $store = new ArrayStore;
    $store->tags('bop')->decrement('foo', 5);
    expect($store->tags('bop')->get('foo'))->toEqual(-5);

    $store->tags('bop')->flush();
    expect($store->tags('bop')->get('foo'))->toBeNull();
});

test('tags cache forever', function () {
    $store = new ArrayStore;
    $tags = ['bop', 'zap'];
    $store->tags($tags)->forever('foo', 'bar');

    expect($store->tags($tags)->get('foo'))->toBe('bar');
});
