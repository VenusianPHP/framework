<?php

use Voyager\Contracts\Events\Dispatcher;
use Voyager\Redis\Connections\PhpRedisConnection;
use Voyager\Redis\Events\CommandExecuted;
use Voyager\Redis\Events\CommandFailed;
use Mockery as m;

test('command failed event is dispatched', function () {
    $exception = new Exception('Test exception');

    $client = m::mock(Redis::class);
    $client->shouldReceive('get')->with('key')->andThrow($exception);

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) use ($exception) {
        return $event instanceof CommandFailed
            && $event->command === 'get'
            && $event->parameters === ['key']
            && $event->exception === $exception;
    }));

    $connection = new PhpRedisConnection($client);
    $connection->setEventDispatcher($events);

    $connection->command('get', ['key']);
})->throws(Exception::class, 'Test exception');

test('command executed event is not dispatched when command fails', function () {
    $exception = new Exception('Test exception');

    $client = m::mock(Redis::class);
    $client->shouldReceive('get')->with('key')->andThrow($exception);

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::type(CommandFailed::class));
    $events->shouldNotReceive('dispatch')->with(m::type(CommandExecuted::class));

    $connection = new PhpRedisConnection($client);
    $connection->setEventDispatcher($events);

    try {
        $connection->command('get', ['key']);
    } catch (Exception $e) {
        // Expected exception
    }
});

test('command failed event contains connection name', function () {
    $exception = new Exception('Test exception');

    $client = m::mock(Redis::class);
    $client->shouldReceive('get')->with('key')->andThrow($exception);

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) {
        return $event instanceof CommandFailed
            && $event->connectionName === 'test-connection';
    }));

    $connection = new PhpRedisConnection($client);
    $connection->setName('test-connection');
    $connection->setEventDispatcher($events);

    try {
        $connection->command('get', ['key']);
    } catch (Exception $e) {
        // Expected exception
    }
});

test('listen for failures registers callback', function () {
    $client = m::mock(Redis::class);

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('listen')->once()->with(CommandFailed::class, m::type('Closure'));

    $connection = new PhpRedisConnection($client);
    $connection->setEventDispatcher($events);

    $connection->listenForFailures(function () {
        // callback
    });
});
