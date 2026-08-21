<?php

use Voyager\Cache\DatabaseStore;
use Voyager\Database\Connection;
use Voyager\Database\PostgresConnection;
use Voyager\Database\SQLiteConnection;

/** The constructor arguments used to build a store over the given connection class. */
function databaseStoreMocks(string $connectionClass = Connection::class): array
{
    return [Mockery::mock($connectionClass), 'table', 'prefix'];
}

/** A plain DatabaseStore backed by a Mockery connection mock of the given class. */
function databaseStoreWithConnection(string $connectionClass = Connection::class): DatabaseStore
{
    return new DatabaseStore(...databaseStoreMocks($connectionClass));
}

test('null is returned when item not found', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('whereIn')->once()->with('key', ['prefixfoo'])->andReturn($table);
    $table->shouldReceive('get')->once()->andReturn(collect([]));

    expect($store->get('foo'))->toBeNull();
});

test('null is returned and item deleted when item is expired', function () {
    $store = Mockery::mock(DatabaseStore::class, databaseStoreMocks())
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $getQuery = Mockery::mock(stdClass::class);
    $getQuery->shouldReceive('whereIn')->once()->with('key', ['prefixfoo'])->andReturn($getQuery);
    $getQuery->shouldReceive('get')->once()->andReturn(collect([(object) ['key' => 'prefixfoo', 'expiration' => 1]]));

    $deleteQuery = Mockery::mock(stdClass::class);
    $deleteQuery->shouldReceive('whereIn')->once()->with('key', ['prefixfoo', 'prefixilluminate:cache:flexible:created:foo'])->andReturn($deleteQuery);
    $deleteQuery->shouldReceive('where')->once()->with('expiration', '<=', Mockery::any())->andReturn($deleteQuery);
    $deleteQuery->shouldReceive('delete')->once()->andReturnNull();

    $store->getConnection()->shouldReceive('table')->twice()->with('table')->andReturn($getQuery, $deleteQuery);

    expect($store->get('foo'))->toBeNull();
});

test('decrypted value is returned when item is valid', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('whereIn')->once()->with('key', ['prefixfoo'])->andReturn($table);
    $table->shouldReceive('get')->once()->andReturn(collect([(object) ['key' => 'prefixfoo', 'value' => serialize('bar'), 'expiration' => 999999999999999]]));

    expect($store->get('foo'))->toBe('bar');
});

test('value is returned on postgres', function () {
    $store = databaseStoreWithConnection(PostgresConnection::class);
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('whereIn')->once()->with('key', ['prefixfoo'])->andReturn($table);
    $table->shouldReceive('get')->once()->andReturn(collect([(object) ['key' => 'prefixfoo', 'value' => base64_encode(serialize('bar')), 'expiration' => 999999999999999]]));

    expect($store->get('foo'))->toBe('bar');
});

test('value is returned on sqlite', function () {
    $store = databaseStoreWithConnection(SQLiteConnection::class);
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('whereIn')->once()->with('key', ['prefixfoo'])->andReturn($table);
    $table->shouldReceive('get')->once()->andReturn(collect([(object) ['key' => 'prefixfoo', 'value' => base64_encode(serialize("\0bar\0")), 'expiration' => 999999999999999]]));

    expect($store->get('foo'))->toBe("\0bar\0");
});

test('value is upserted', function () {
    $store = Mockery::mock(DatabaseStore::class, databaseStoreMocks())
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $store->shouldReceive('getTime')->once()->andReturn(1);
    $table->shouldReceive('upsert')->once()->with([['key' => 'prefixfoo', 'value' => serialize('bar'), 'expiration' => 61]], 'key')->andReturnTrue();

    $result = $store->put('foo', 'bar', 60);

    expect($result)->toBeTrue();
});

test('value is upserted on postgres', function () {
    $store = Mockery::mock(DatabaseStore::class, databaseStoreMocks(PostgresConnection::class))
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $store->shouldReceive('getTime')->once()->andReturn(1);
    $table->shouldReceive('upsert')->once()->with([['key' => 'prefixfoo', 'value' => base64_encode(serialize("\0")), 'expiration' => 61]], 'key')->andReturn(1);

    $result = $store->put('foo', "\0", 60);

    expect($result)->toBeTrue();
});

test('value is upserted on sqlite', function () {
    $store = Mockery::mock(DatabaseStore::class, databaseStoreMocks(SQLiteConnection::class))
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $store->shouldReceive('getTime')->once()->andReturn(1);
    $table->shouldReceive('upsert')->once()->with([['key' => 'prefixfoo', 'value' => base64_encode(serialize("\0")), 'expiration' => 61]], 'key')->andReturn(1);

    $result = $store->put('foo', "\0", 60);

    expect($result)->toBeTrue();
});

test('forever calls store item with really long time', function () {
    $store = Mockery::mock(DatabaseStore::class, databaseStoreMocks())->makePartial();
    $store->shouldReceive('put')->once()->with('foo', 'bar', 315360000)->andReturn(true);

    $result = $store->forever('foo', 'bar');

    expect($result)->toBeTrue();
});

test('items may be removed from cache', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('whereIn')->once()->with('key', ['prefixfoo', 'prefixilluminate:cache:flexible:created:foo'])->andReturn($table);
    $table->shouldReceive('delete')->once();

    expect($store->forget('foo'))->toBeTrue();
});

test('items may be flushed from cache', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('delete')->once()->andReturn(2);

    $result = $store->flush();

    expect($result)->toBeTrue();
});

test('increment returns correct values', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $cache = Mockery::mock(stdClass::class);

    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn(null);
    expect($store->increment('foo'))->toBeFalse();

    $cache->value = serialize('bar');
    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn($cache);
    expect($store->increment('foo'))->toBeFalse();

    $cache->value = serialize(2);
    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn($cache);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('update')->once()->with(['value' => serialize(3)]);
    expect($store->increment('foo'))->toEqual(3);
});

test('decrement returns correct values', function () {
    $store = databaseStoreWithConnection();
    $table = Mockery::mock(stdClass::class);
    $cache = Mockery::mock(stdClass::class);

    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn(null);
    expect($store->decrement('foo'))->toBeFalse();

    $cache->value = serialize('bar');
    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixfoo')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn($cache);
    expect($store->decrement('foo'))->toBeFalse();

    $cache->value = serialize(3);
    $store->getConnection()->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn ($closure) => $closure());
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixbar')->andReturn($table);
    $table->shouldReceive('lockForUpdate')->once()->andReturn($table);
    $table->shouldReceive('first')->once()->andReturn($cache);
    $store->getConnection()->shouldReceive('table')->once()->with('table')->andReturn($table);
    $table->shouldReceive('where')->once()->with('key', 'prefixbar')->andReturn($table);
    $table->shouldReceive('update')->once()->with(['value' => serialize(2)]);
    expect($store->decrement('bar'))->toEqual(2);
});
