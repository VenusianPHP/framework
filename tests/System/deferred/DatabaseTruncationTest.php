<?php

use Voyager\Config\Repository;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\Connection;
use Voyager\Database\Schema\Builder;
use Voyager\Database\Schema\PostgresBuilder;
use Voyager\System\Testing\DatabaseTruncation;

uses(DatabaseTruncation::class);

/**
 * A connection double whose schema reports the given tables, and which records
 * every table truncated through it into $actual.
 */
function arrangeTruncationConnection(
    ?array &$actual, array $allTables, string $prefix = '', ?string $builder = null, ?array $schemas = []
): Connection {
    $actual = [];

    $schema = Mockery::mock($builder ?? Builder::class);
    $schema->shouldReceive('getTables')->with($schemas)->once()->andReturn(
        empty($schemas)
            ? $allTables
            : array_filter($allTables, fn ($table) => in_array($table['schema'], $schemas))
    );
    $schema->shouldReceive('getCurrentSchemaListing')->once()->andReturn($schemas);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn($prefix);
    $connection->shouldReceive('getEventDispatcher')->once()->andReturn($dispatcher = Mockery::mock(Dispatcher::class));
    $connection->shouldReceive('unsetEventDispatcher')->once();
    $connection->shouldReceive('setEventDispatcher')->once()->with($dispatcher);
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
    $connection->shouldReceive('withoutTablePrefix')->andReturnUsing(function ($callback) use ($connection) {
        $callback($connection);
    });
    $connection->shouldReceive('table')
        ->andReturnUsing(function (string $tableName) use (&$actual) {
            $actual[] = $tableName;

            $table = Mockery::mock();
            $table->shouldReceive('exists')->andReturnTrue();
            $table->shouldReceive('truncate');

            return $table;
        });

    return $connection;
}

beforeEach(function () {
    $this->app['config'] = new Repository([
        'database' => [
            'migrations' => [
                'table' => 'migrations',
            ],
        ],
    ]);
});

afterEach(function () {
    $this->app = null;
    static::$allTables = [];
    $this->tablesToTruncate = null;
    $this->exceptTables = null;
});

test('every table is truncated', function () {
    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => null, 'name' => 'foo', 'schema_qualified_name' => 'foo'],
        ['schema' => null, 'name' => 'bar', 'schema_qualified_name' => 'bar'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['foo', 'bar']);
});

test('the tablesToTruncate property narrows the list', function () {
    $this->tablesToTruncate = ['foo', 'bar', 'qux'];

    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => null, 'name' => 'migrations', 'schema_qualified_name' => 'migrations'],
        ['schema' => null, 'name' => 'foo', 'schema_qualified_name' => 'foo'],
        ['schema' => null, 'name' => 'bar', 'schema_qualified_name' => 'bar'],
        ['schema' => null, 'name' => 'baz', 'schema_qualified_name' => 'baz'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['foo', 'bar']);
});

test('the exceptTables property excludes from the list', function () {
    $this->exceptTables = ['baz', 'qux'];

    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => null, 'name' => 'migrations', 'schema_qualified_name' => 'migrations'],
        ['schema' => null, 'name' => 'foo', 'schema_qualified_name' => 'foo'],
        ['schema' => null, 'name' => 'bar', 'schema_qualified_name' => 'bar'],
        ['schema' => null, 'name' => 'baz', 'schema_qualified_name' => 'baz'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['foo', 'bar']);
});

test('schema qualified tables are truncated, migrations excepted', function () {
    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => 'public', 'name' => 'migrations', 'schema_qualified_name' => 'public.migrations'],
        ['schema' => 'public', 'name' => 'foo', 'schema_qualified_name' => 'public.foo'],
        ['schema' => 'public', 'name' => 'bar', 'schema_qualified_name' => 'public.bar'],
        ['schema' => 'private', 'name' => 'migrations', 'schema_qualified_name' => 'private.migrations'],
        ['schema' => 'private', 'name' => 'foo', 'schema_qualified_name' => 'private.foo'],
        ['schema' => 'private', 'name' => 'baz', 'schema_qualified_name' => 'private.baz'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['public.foo', 'public.bar', 'private.foo', 'private.baz']);
});

test('the tablesToTruncate property matches bare and schema qualified names', function () {
    $this->tablesToTruncate = ['foo', 'public.bar'];

    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => 'public', 'name' => 'migrations', 'schema_qualified_name' => 'public.migrations'],
        ['schema' => 'public', 'name' => 'foo', 'schema_qualified_name' => 'public.foo'],
        ['schema' => 'public', 'name' => 'bar', 'schema_qualified_name' => 'public.bar'],
        ['schema' => 'public', 'name' => 'baz', 'schema_qualified_name' => 'public.baz'],
        ['schema' => 'private', 'name' => 'migrations', 'schema_qualified_name' => 'private.migrations'],
        ['schema' => 'private', 'name' => 'foo', 'schema_qualified_name' => 'private.foo'],
        ['schema' => 'private', 'name' => 'bar', 'schema_qualified_name' => 'private.bar'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['public.foo', 'public.bar', 'private.foo']);
});

test('the exceptTables property matches bare and schema qualified names', function () {
    $this->exceptTables = ['foo', 'public.bar'];

    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => 'public', 'name' => 'migrations', 'schema_qualified_name' => 'public.migrations'],
        ['schema' => 'public', 'name' => 'foo', 'schema_qualified_name' => 'public.foo'],
        ['schema' => 'public', 'name' => 'bar', 'schema_qualified_name' => 'public.bar'],
        ['schema' => 'public', 'name' => 'baz', 'schema_qualified_name' => 'public.baz'],
        ['schema' => 'private', 'name' => 'migrations', 'schema_qualified_name' => 'private.migrations'],
        ['schema' => 'private', 'name' => 'foo', 'schema_qualified_name' => 'private.foo'],
        ['schema' => 'private', 'name' => 'bar', 'schema_qualified_name' => 'private.bar'],
    ]);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['public.baz', 'private.bar']);
});

test('a connection prefix is honoured when excepting the migrations table', function () {
    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => 'public', 'name' => 'my_migrations', 'schema_qualified_name' => 'public.my_migrations'],
        ['schema' => 'public', 'name' => 'my_foo', 'schema_qualified_name' => 'public.my_foo'],
        ['schema' => 'public', 'name' => 'my_baz', 'schema_qualified_name' => 'public.my_baz'],
        ['schema' => 'private', 'name' => 'my_migrations', 'schema_qualified_name' => 'private.my_migrations'],
        ['schema' => 'private', 'name' => 'my_foo', 'schema_qualified_name' => 'private.my_foo'],
    ], 'my_');

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['public.my_foo', 'public.my_baz', 'private.my_foo']);
});

test('on pgsql only the schemas on the search path are truncated', function () {
    $connection = arrangeTruncationConnection($truncatedTables, [
        ['schema' => 'public', 'name' => 'migrations', 'schema_qualified_name' => 'public.migrations'],
        ['schema' => 'public', 'name' => 'foo', 'schema_qualified_name' => 'public.foo'],
        ['schema' => 'public', 'name' => 'bar', 'schema_qualified_name' => 'public.bar'],
        ['schema' => 'my_schema', 'name' => 'foo', 'schema_qualified_name' => 'my_schema.foo'],
        ['schema' => 'my_schema', 'name' => 'baz', 'schema_qualified_name' => 'my_schema.baz'],
        ['schema' => 'private', 'name' => 'migrations', 'schema_qualified_name' => 'private.migrations'],
        ['schema' => 'private', 'name' => 'foo', 'schema_qualified_name' => 'private.foo'],
        ['schema' => 'private', 'name' => 'baz', 'schema_qualified_name' => 'private.baz'],
    ], '', PostgresBuilder::class, ['my_schema', 'public']);

    $this->truncateTablesForConnection($connection, 'test');

    expect($truncatedTables)->toEqual(['public.foo', 'public.bar', 'my_schema.foo', 'my_schema.baz']);
});
