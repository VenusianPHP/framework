<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Processors\PostgresProcessor;
use Voyager\Database\Schema\Grammars\PostgresGrammar;
use Voyager\Database\Schema\PostgresBuilder;
use Mockery as m;

function postgresBuilderConnection()
{
    return m::mock(Connection::class);
}

function postgresBuilderBuilder($connection)
{
    return new PostgresBuilder($connection);
}

function postgresBuilderGrammar()
{
    return new PostgresGrammar;
}

test('create database', function () {
    $connection = m::mock(Connection::class);
    $grammar = new PostgresGrammar($connection);

    $connection->shouldReceive('getConfig')->once()->with('charset')->andReturn('utf8');
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('statement')->once()->with(
        'create database "my_temporary_database" encoding "utf8"'
    )->andReturn(true);

    $builder = postgresBuilderBuilder($connection);
    $builder->createDatabase('my_temporary_database');
});

test('drop database if exists', function () {
    $connection = m::mock(Connection::class);
    $grammar = new PostgresGrammar($connection);

    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('statement')->once()->with(
        'drop database if exists "my_database_a"'
    )->andReturn(true);

    $builder = postgresBuilderBuilder($connection);

    $builder->dropDatabaseIfExists('my_database_a');
});

test('has table when schema unqualified and search path missing', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('schema')->andReturn(null);
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileTableExists')->andReturn('sql');
    $connection->shouldReceive('scalar')->with('sql')->andReturn(1);
    $connection->shouldReceive('getTablePrefix');
    $builder = postgresBuilderBuilder($connection);

    expect($builder->hasTable('foo'))->toBeTrue();
    expect($builder->hasTable('public.foo'))->toBeTrue();
});

test('has table when schema unqualified and search path filled', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('myapp,public');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileTableExists')->andReturn('sql');
    $connection->shouldReceive('scalar')->with('sql')->andReturn(1);
    $connection->shouldReceive('getTablePrefix');
    $builder = postgresBuilderBuilder($connection);

    expect($builder->hasTable('foo'))->toBeTrue();
    expect($builder->hasTable('myapp.foo'))->toBeTrue();
});

test('has table when schema unqualified and search path fallback filled', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('schema')->andReturn(['myapp', 'public']);
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileTableExists')->andReturn('sql');
    $connection->shouldReceive('scalar')->with('sql')->andReturn(1);
    $connection->shouldReceive('getTablePrefix');
    $builder = postgresBuilderBuilder($connection);

    expect($builder->hasTable('foo'))->toBeTrue();
    expect($builder->hasTable('myapp.foo'))->toBeTrue();
});

test('has table when schema unqualified and search path is user variable', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('username')->andReturn('foouser');
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('$user');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileTableExists')->andReturn('sql');
    $connection->shouldReceive('scalar')->with('sql')->andReturn(1);
    $connection->shouldReceive('getTablePrefix');
    $builder = postgresBuilderBuilder($connection);

    expect($builder->hasTable('foo'))->toBeTrue();
    expect($builder->hasTable('foouser.foo'))->toBeTrue();
});

test('has table when schema qualified and search path mismatches', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('public');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileTableExists')->andReturn('sql');
    $connection->shouldReceive('scalar')->with('sql')->andReturn(1);
    $connection->shouldReceive('getTablePrefix');
    $builder = postgresBuilderBuilder($connection);

    expect($builder->hasTable('myapp.foo'))->toBeTrue();
});

test('has table when database and schema qualified and search path mismatches', function () {
    $connection = postgresBuilderConnection();
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $builder = postgresBuilderBuilder($connection);

    $builder->hasTable('mydatabase.myapp.foo');
})->throws(\InvalidArgumentException::class);

test('get column listing when schema unqualified and search path missing', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('schema')->andReturn(null);
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileColumns')->with(null, 'foo')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'some_column']]);
    $connection->shouldReceive('getTablePrefix');
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processColumns')->andReturn([['name' => 'some_column']]);
    $builder = postgresBuilderBuilder($connection);

    $builder->getColumnListing('foo');
});

test('get column listing when schema unqualified and search path filled', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('myapp,public');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileColumns')->with(null, 'foo')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'some_column']]);
    $connection->shouldReceive('getTablePrefix');
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processColumns')->andReturn([['name' => 'some_column']]);
    $builder = postgresBuilderBuilder($connection);

    $builder->getColumnListing('foo');
});

test('get column listing when schema unqualified and search path is user variable', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('username')->andReturn('foouser');
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('$user');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileColumns')->with(null, 'foo')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'some_column']]);
    $connection->shouldReceive('getTablePrefix');
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processColumns')->andReturn([['name' => 'some_column']]);
    $builder = postgresBuilderBuilder($connection);

    $builder->getColumnListing('foo');
});

test('get column listing when schema qualified and search path mismatches', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('public');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $grammar->shouldReceive('compileColumns')->with('myapp', 'foo')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'some_column']]);
    $connection->shouldReceive('getTablePrefix');
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processColumns')->andReturn([['name' => 'some_column']]);
    $builder = postgresBuilderBuilder($connection);

    $builder->getColumnListing('myapp.foo');
});

test('get column when database and schema qualified and search path mismatches', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('public');
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $builder = postgresBuilderBuilder($connection);

    $builder->getColumnListing('mydatabase.myapp.foo');
})->throws(\InvalidArgumentException::class);

test('drop all tables when search path is string', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('public');
    $connection->shouldReceive('getConfig')->with('dont_drop')->andReturn(['foo']);
    $grammar = m::mock(PostgresGrammar::class);
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $grammar->shouldReceive('compileTables')->andReturn('sql');
    $processor->shouldReceive('processTables')->once()->andReturn([['name' => 'users', 'schema' => 'public', 'schema_qualified_name' => 'public.users']]);
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'users', 'schema' => 'public', 'schema_qualified_name' => 'public.users']]);
    $grammar->shouldReceive('compileDropAllTables')->with(['public.users'])->andReturn('drop table "public"."users" cascade');
    $connection->shouldReceive('statement')->with('drop table "public"."users" cascade');
    $builder = postgresBuilderBuilder($connection);

    $builder->dropAllTables();
});

test('drop all tables when search path is string of many', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('username')->andReturn('foouser');
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn('"$user", public, foo_bar-Baz.Áüõß');
    $connection->shouldReceive('getConfig')->with('dont_drop')->andReturn(['foo']);
    $grammar = m::mock(PostgresGrammar::class);
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processTables')->once()->andReturn([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
    $grammar->shouldReceive('compileTables')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
    $grammar->shouldReceive('compileDropAllTables')->with(['foouser.users'])->andReturn('drop table "foouser"."users" cascade');
    $connection->shouldReceive('statement')->with('drop table "foouser"."users" cascade');
    $builder = postgresBuilderBuilder($connection);

    $builder->dropAllTables();
});

test('drop all tables when search path is array of many', function () {
    $connection = postgresBuilderConnection();
    $connection->shouldReceive('getConfig')->with('username')->andReturn('foouser');
    $connection->shouldReceive('getConfig')->with('search_path')->andReturn([
        '$user',
        '"dev"',
        "'test'",
        'spaced schema',
    ]);
    $connection->shouldReceive('getConfig')->with('dont_drop')->andReturn(['foo']);
    $grammar = m::mock(PostgresGrammar::class);
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processTables')->once()->andReturn([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
    $grammar->shouldReceive('compileTables')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->with('sql')->andReturn([['name' => 'users', 'schema' => 'foouser', 'schema_qualified_name' => 'foouser.users']]);
    $grammar->shouldReceive('compileDropAllTables')->with(['foouser.users'])->andReturn('drop table "foouser"."users" cascade');
    $connection->shouldReceive('statement')->with('drop table "foouser"."users" cascade');
    $builder = postgresBuilderBuilder($connection);

    $builder->dropAllTables();
});
