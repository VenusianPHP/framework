<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Processors\PostgresProcessor;
use Voyager\Database\Schema\Grammars\PostgresGrammar;
use Voyager\Database\Schema\PostgresBuilder;
use Mockery as m;

test('has table', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(PostgresGrammar::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $builder = new PostgresBuilder($connection);
    $grammar->shouldReceive('compileTableExists')->twice()->andReturn('sql');
    $connection->shouldReceive('getTablePrefix')->twice()->andReturn('prefix_');
    $connection->shouldReceive('scalar')->twice()->with('sql')->andReturn(1);

    expect($builder->hasTable('table'))->toBeTrue();
    expect($builder->hasTable('public.table'))->toBeTrue();
});

test('get column listing', function () {
    $connection = m::mock(Connection::class);
    $grammar = m::mock(PostgresGrammar::class);
    $processor = m::mock(PostgresProcessor::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $grammar->shouldReceive('compileColumns')->with(null, 'prefix_table')->once()->andReturn('sql');
    $processor->shouldReceive('processColumns')->once()->andReturn([['name' => 'column']]);
    $builder = new PostgresBuilder($connection);
    $connection->shouldReceive('getTablePrefix')->once()->andReturn('prefix_');
    $connection->shouldReceive('selectFromWriteConnection')->once()->with('sql')->andReturn([['name' => 'column']]);

    expect($builder->getColumnListing('table'))->toEqual(['column']);
});
