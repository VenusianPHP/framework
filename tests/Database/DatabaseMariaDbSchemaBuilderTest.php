<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Processors\MariaDbProcessor;
use Voyager\Database\Schema\Grammars\MariaDbGrammar;
use Voyager\Database\Schema\MariaDbBuilder;
use Mockery as m;

test('has table', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(MariaDbGrammar::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('db');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $builder = new MariaDbBuilder($connection);
    $grammar->shouldReceive('compileTableExists')->once()->andReturn('sql');
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('scalar')->once()->with('sql')->andReturn(1);

    expect($builder->hasTable('table'))->toBeTrue();
});

test('get column listing', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(MariaDbGrammar::class);
    $processor = m::mock(MariaDbProcessor::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('db');
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $grammar->shouldReceive('compileColumns')->with(null, 'prefix_table')->once()->andReturn('sql');
    $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'column']]);
    $builder = new MariaDbBuilder($connection);
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'column']]);

    expect($builder->getColumnListing('table'))->toEqual(['column']);
});
