<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Builder;
use Voyager\Database\Query\Expression;
use Voyager\Database\Query\Grammars\Grammar;
use Mockery as m;
use ReflectionClass;

test('where raw returns string when expression passed', function () {
    $builder = m::mock(Builder::class);
    $grammar = new Grammar(m::mock(Connection::class));
    $reflection = new ReflectionClass($grammar);
    $method = $reflection->getMethod('whereRaw');
    $expressionArray = ['sql' => new Expression('select * from "users"')];

    $rawQuery = $method->invoke($grammar, $builder, $expressionArray);

    expect($rawQuery)->toBe('select * from "users"');
});

test('where raw returns string when string passed', function () {
    $builder = m::mock(Builder::class);
    $grammar = new Grammar(m::mock(Connection::class));
    $reflection = new ReflectionClass($grammar);
    $method = $reflection->getMethod('whereRaw');
    $stringArray = ['sql' => 'select * from "users"'];

    $rawQuery = $method->invoke($grammar, $builder, $stringArray);

    expect($rawQuery)->toBe('select * from "users"');
});

test('compile orders accepts expression', function () {
    $builder = m::mock(Builder::class);
    $grammar = new Grammar(m::mock(Connection::class));

    // compileOrders() calls $query->getGrammar() → return our $grammar
    $builder->shouldReceive('getGrammar')->andReturn($grammar);

    $orders = [
        ['sql' => new Expression('length("name") desc')], // mimics orderByRaw(DB::raw(...))
    ];

    $ref = new \ReflectionClass($grammar);
    $method = $ref->getMethod('compileOrders'); // protected
    $sql = $method->invoke($grammar, $builder, $orders);

    expect(strtolower($sql))->toBe('order by length("name") desc');
});

test('compile orders accepts expression with placeholders', function () {
    $builder = m::mock(Builder::class);
    $grammar = new Grammar(m::mock(Connection::class));
    $builder->shouldReceive('getGrammar')->andReturn($grammar);

    $orders = [
        ['sql' => new Expression('field(status, ?, ?) asc')],
    ];

    $ref = new \ReflectionClass($grammar);
    $method = $ref->getMethod('compileOrders');
    $sql = $method->invoke($grammar, $builder, $orders);

    expect(strtolower($sql))->toBe('order by field(status, ?, ?) asc');
});
