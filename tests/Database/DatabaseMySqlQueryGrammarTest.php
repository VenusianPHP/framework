<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Builder;
use Voyager\Database\Query\Grammars\MySqlGrammar;
use Voyager\Database\Query\Processors\Processor;
use Mockery as m;

function mysqlQueryGrammarBuilder()
{
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getDatabaseName')->andReturn('database');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $grammar = new MySqlGrammar($connection);
    $processor = m::mock(Processor::class);

    return new Builder($connection, $grammar, $processor);
}

test('to raw sql', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('escape')->with('foo', false)->andReturn("'foo'");
    $grammar = new MySqlGrammar($connection);

    $query = $grammar->substituteBindingsIntoRawSql(
        'select * from "users" where \'Hello\\\'World?\' IS NOT NULL AND "email" = ?',
        ['foo'],
    );

    expect($query)->toBe('select * from "users" where \'Hello\\\'World?\' IS NOT NULL AND "email" = \'foo\'');
});

test('timeout', function () {
    $builder = mysqlQueryGrammarBuilder();
    $builder->select('*')->from('users')->where('email', 'like', '%test%')->timeout(60);
    expect($builder->toSql())->toBe(
        'select /*+ MAX_EXECUTION_TIME(60000) */ * from `users` where `email` like ?'
    );
});

test('timeout with distinct', function () {
    $builder = mysqlQueryGrammarBuilder();
    $builder->distinct()->select('*')->from('users')->timeout(30);
    expect($builder->toSql())->toBe(
        'select /*+ MAX_EXECUTION_TIME(30000) */ distinct * from `users`'
    );
});

test('timeout with aggregate', function () {
    $builder = mysqlQueryGrammarBuilder();
    $builder->from('users')->timeout(10);
    $builder->aggregate = ['function' => 'count', 'columns' => ['*']];
    expect($builder->toSql())->toBe(
        'select /*+ MAX_EXECUTION_TIME(10000) */ count(*) as aggregate from `users`'
    );
});

test('timeout null removes timeout', function () {
    $builder = mysqlQueryGrammarBuilder();
    $builder->select('*')->from('users')->timeout(60)->timeout(null);
    expect($builder->toSql())->toBe('select * from `users`');
});

test('timeout throws exception for negative value', function () {
    $builder = mysqlQueryGrammarBuilder();
    $builder->select('*')->from('users')->timeout(-1);
})->throws(InvalidArgumentException::class);
