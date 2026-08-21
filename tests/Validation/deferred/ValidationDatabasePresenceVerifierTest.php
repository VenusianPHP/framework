<?php

use Voyager\Database\ConnectionResolverInterface;
use Voyager\Validation\DatabasePresenceVerifier;

test('basic count', function () {
        $verifier = new DatabasePresenceVerifier($db = Mockery::mock(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->shouldReceive('connection')->once()->with('connection')->andReturn($conn = Mockery::mock(stdClass::class));
        $conn->shouldReceive('table')->once()->with('table')->andReturn($builder = Mockery::mock(stdClass::class));
        $builder->shouldReceive('useWritePdo')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('column', '=', 'value')->andReturn($builder);
        $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin'];
        $builder->shouldReceive('whereNull')->with('foo');
        $builder->shouldReceive('whereNotNull')->with('bar');
        $builder->shouldReceive('where')->with('baz', 'taylor');
        $builder->shouldReceive('where')->with('faz', true);
        $builder->shouldReceive('where')->with('not', '!=', 'admin');
        $builder->shouldReceive('count')->once()->andReturn(100);

        expect($verifier->getCount('table', 'column', 'value', null, null, $extra))->toEqual(100);
    });

test('basic count with closures', function () {
        $verifier = new DatabasePresenceVerifier($db = Mockery::mock(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->shouldReceive('connection')->once()->with('connection')->andReturn($conn = Mockery::mock(stdClass::class));
        $conn->shouldReceive('table')->once()->with('table')->andReturn($builder = Mockery::mock(stdClass::class));
        $builder->shouldReceive('useWritePdo')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('column', '=', 'value')->andReturn($builder);
        $closure = function ($query) {
            $query->where('closure', 1);
        };
        $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin', 0 => $closure];
        $builder->shouldReceive('whereNull')->with('foo');
        $builder->shouldReceive('whereNotNull')->with('bar');
        $builder->shouldReceive('where')->with('baz', 'taylor');
        $builder->shouldReceive('where')->with('faz', true);
        $builder->shouldReceive('where')->with('not', '!=', 'admin');
        $builder->shouldReceive('where')->with(Mockery::type(Closure::class))->andReturnUsing(function () use ($builder, $closure) {
            $closure($builder);
        });
        $builder->shouldReceive('where')->with('closure', 1);
        $builder->shouldReceive('count')->once()->andReturn(100);

        expect($verifier->getCount('table', 'column', 'value', null, null, $extra))->toEqual(100);
    });

test('get count with valid exclude id', function () {
        $verifier = new DatabasePresenceVerifier($db = Mockery::mock(ConnectionResolverInterface::class));
        $verifier->setConnection('connection');
        $db->shouldReceive('connection')->once()->with('connection')->andReturn($conn = Mockery::mock(stdClass::class));
        $conn->shouldReceive('table')->once()->with('table')->andReturn($builder = Mockery::mock(stdClass::class));
        $builder->shouldReceive('useWritePdo')->once()->andReturn($builder);
        $builder->shouldReceive('where')->with('column', '=', 'value')->andReturn($builder);
        $builder->shouldReceive('where')->with('id', '<>', 123)->andReturn($builder);
        $builder->shouldReceive('count')->once()->andReturn(100);

        expect($verifier->getCount('table', 'column', 'value', 123, 'id', []))->toEqual(100);
    });

