<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Processors\MySqlProcessor;
use Voyager\Database\Schema\Grammars\MySqlGrammar;
use Voyager\Database\Schema\MySqlBuilder;
use Mockery as m;

test('has table', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(MySqlGrammar::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('db');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $builder = new MySqlBuilder($connection);
    $grammar->shouldReceive('compileTableExists')->once()->andReturn('sql');
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('scalar')->once()->with('sql')->andReturn(1);

    expect($builder->hasTable('table'))->toBeTrue();
});

test('get column listing', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(MySqlGrammar::class);
    $processor = m::mock(MySqlProcessor::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('db');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $grammar->shouldReceive('compileColumns')->with(null, 'prefix_table')->once()->andReturn('sql');
    $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'column']]);
    $builder = new MySqlBuilder($connection);
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'column']]);

    expect($builder->getColumnListing('table'))->toEqual(['column']);
});
