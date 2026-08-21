<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Processors\Processor;
use Voyager\Database\Schema\Builder;
use Voyager\Database\Schema\Grammars\Grammar;
use Mockery as m;

test('create database', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(stdClass::class);
    $grammar->shouldReceive('compileCreateDatabase')->andReturn('sql');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('statement')->with('sql')->andReturnTrue();
    $builder = new Builder($connection);

    $this->assertTrue($builder->createDatabase('foo'));
});

test('drop database if exists', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(stdClass::class);
    $grammar->shouldReceive('compileDropDatabaseIfExists')->andReturn('sql');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('statement')->with('sql')->andReturnTrue();
    $builder = new Builder($connection);

    $this->assertTrue($builder->dropDatabaseIfExists('foo'));
});

test('has table correctly calls grammar', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(Grammar::class);
    $processor = m::mock(Processor::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $builder = new Builder($connection);
    $grammar->shouldReceive('compileTableExists');
    $grammar->shouldReceive('compileTables')->once()->andReturn('sql');
    $processor->shouldReceive('processTables')->once()->andReturn([['name' => 'prefix_table']]);
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'prefix_table']]);

    $this->assertTrue($builder->hasTable('table'));
});

test('table has columns', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(stdClass::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $builder = m::mock(Builder::class.'[getColumnListing]', [$connection]);
    $builder->shouldReceive('getColumnListing')->with('users')->twice()->andReturn(['id', 'firstname']);

    $this->assertTrue($builder->hasColumns('users', ['id', 'firstname']));
    $this->assertFalse($builder->hasColumns('users', ['id', 'address']));
});

test('get column type adds prefix', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(Grammar::class);
    $processor = m::mock(Processor::class);
    $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'id', 'type_name' => 'integer']]);
    $builder = new Builder($connection);
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $grammar->shouldReceive('compileColumns')->once()->with(null, 'prefix_users')->andReturn('sql');
    $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'id', 'type_name' => 'integer']]);

    $this->assertSame('integer', $builder->getColumnType('users', 'id'));
});
