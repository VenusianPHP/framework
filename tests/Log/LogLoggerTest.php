<?php

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Tests\Log\Fixtures\SpyingArrayable;
use Voyager\Contracts\Events\Dispatcher as DispatcherContract;
use Voyager\Events\Dispatcher;
use Voyager\Log\Events\MessageLogged;
use Voyager\Log\Logger;

test('a level method passes the message straight on to monolog', function () {
    $writer = new Logger($monolog = Mockery::mock(Monolog::class));
    $monolog->shouldReceive('isHandling')->with('error')->andReturn(true);
    $monolog->shouldReceive('error')->once()->with('foo', []);

    $writer->error('foo');
});

describe('context', function () {
    test('withContext is added to every subsequent log', function () {
        $writer = new Logger($monolog = Mockery::mock(Monolog::class));
        $writer->withContext(['bar' => 'baz']);

        $monolog->shouldReceive('isHandling')->with('error')->andReturn(true);
        $monolog->shouldReceive('error')->once()->with('foo', ['bar' => 'baz']);

        $writer->error('foo');
    });

    test('withoutContext flushes the whole context', function () {
        $writer = new Logger($monolog = Mockery::mock(Monolog::class));
        $writer->withContext(['bar' => 'baz']);
        $writer->withoutContext();

        $monolog->shouldReceive('isHandling')->with('error')->andReturn(true);
        $monolog->expects('error')->with('foo', []);

        $writer->error('foo');
    });

    test('withoutContext can drop named keys only', function () {
        $writer = new Logger($monolog = Mockery::mock(Monolog::class));
        $writer->withContext(['bar' => 'baz', 'forget' => 'me']);
        $writer->withoutContext(['forget']);

        $monolog->shouldReceive('isHandling')->with('error')->andReturn(true);
        $monolog->shouldReceive('error')->once()->with('foo', ['bar' => 'baz']);

        $writer->error('foo');
    });

    test('successive withContext calls merge, and withoutContext prunes', function () {
        $writer = new Logger($monolog = Mockery::mock(Monolog::class));

        $writer->withContext(['user_id' => 123, 'action' => 'login']);
        $writer->withContext(['ip' => '127.0.0.1', 'timestamp' => '1986-10-29']);
        $writer->withoutContext(['timestamp']);

        $monolog->shouldReceive('isHandling')->with('info')->andReturn(true);
        $monolog->shouldReceive('info')->once()->with('User action', [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '127.0.0.1',
        ]);

        $writer->info('User action');
    });
});

describe('events', function () {
    test('logging fires a MessageLogged event', function () {
        $writer = new Logger($monolog = Mockery::mock(Monolog::class), $events = new Dispatcher);
        $monolog->shouldReceive('isHandling')->with('error')->andReturn(true);
        $monolog->shouldReceive('error')->once()->with('foo', []);

        $events->listen(MessageLogged::class, function ($event) {
            $_SERVER['__log.level'] = $event->level;
            $_SERVER['__log.message'] = $event->message;
            $_SERVER['__log.context'] = $event->context;
        });

        $writer->error('foo');

        expect(isset($_SERVER['__log.level']))->toBeTrue();
        expect($_SERVER['__log.level'])->toBe('error');
        unset($_SERVER['__log.level']);
        expect(isset($_SERVER['__log.message']))->toBeTrue();
        expect($_SERVER['__log.message'])->toBe('foo');
        unset($_SERVER['__log.message']);
        expect(isset($_SERVER['__log.context']))->toBeTrue();
        expect($_SERVER['__log.context'])->toEqual([]);
        unset($_SERVER['__log.context']);
    });

    test('the listen shortcut needs a dispatcher', function () {
        (new Logger(Mockery::mock(Monolog::class)))->listen(function () {
            //
        });
    })->throws(RuntimeException::class, 'Events dispatcher has not been set.');

    test('the listen shortcut registers a MessageLogged listener', function () {
        $writer = new Logger(Mockery::mock(Monolog::class), $events = Mockery::mock(DispatcherContract::class));

        $callback = function () {
            return 'success';
        };
        $events->shouldReceive('listen')->with(MessageLogged::class, $callback)->once();

        $writer->listen($callback);
    });
});

describe('lazy serialization', function () {
    test('an Arrayable message is not serialized when the level is not handled', function () {
        $monolog = new Monolog('test');
        $monolog->pushHandler(new TestHandler(Level::Error));

        $arrayable = new SpyingArrayable;

        (new Logger($monolog))->debug($arrayable);

        expect($arrayable->wasCalled)->toBeFalse();
    });

    test('an Arrayable message is serialized when the level is handled', function () {
        $monolog = new Monolog('test');
        $monolog->pushHandler($handler = new TestHandler(Level::Debug));

        $arrayable = new SpyingArrayable;

        (new Logger($monolog))->debug($arrayable);

        expect($arrayable->wasCalled)->toBeTrue()
            ->and($handler->hasDebugRecords())->toBeTrue();
    });
});
