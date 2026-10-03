<?php

use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Resources\Sleeper;
use Voyager\IOPools\Resources\Pollable;
use Voyager\IOPools\Resources\WakeSource;
use Voyager\IOPools\Waiter\Wakes\Readable;
use Voyager\IOPools\Waiter\Wakes\ControlSignal;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\Contracts\IOPools\IOPoolsException;

function loop(int $pace_ms = 5): EventLoop
{
    $registry = new ResourceRegistry();

    return new EventLoop(
        $registry,
        new LoopWaiter($registry, new StreamSelectWaiterBackend(), $pace_ms * 1_000_000),
        new GuzzlePromiseEngine(),
    );
}

/** A polled resource that calls $on_tick every turn. */
function pollable(Closure $on_tick): Pollable
{
    return new class($on_tick) extends Pollable {
        public function __construct(private readonly Closure $on_tick) {}

        public function tick(): void { ($this->on_tick)(); }
    };
}

/** A sleeper that records its name each time it holds the sleep. */
function sleeper(string $name, array &$slept): Sleeper
{
    return new class($name, $slept) extends Sleeper {
        public function __construct(private readonly string $name, private array &$slept) {}

        public function sleep(int $budget_ns): void { $this->slept[] = $this->name; }

        public function tick(): void {}
    };
}

/** A wake source that declares the given wakes and keeps what fired. */
function wakeSource(array $wakes, ?Closure $on_woke = null): WakeSource
{
    return new class($wakes, $on_woke) extends WakeSource {
        public array $fired = [];

        public function __construct(private readonly array $declared, private readonly ?Closure $on_woke) {}

        public function wakes(): array { return $this->declared; }

        public function woke(array $fired): void
        {
            $this->fired = [...$this->fired, ...$fired];
            $this->on_woke && ($this->on_woke)($fired);
        }
    };
}

it('wakes a resource when its stream turns readable and tells it which wake fired', function () {
    $loop = loop();
    [$read, $write] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    $source = wakeSource([new Readable($read)], fn () => fread($read, 64));
    $loop->resource('pipe', $source);

    fwrite($write, 'x');
    $loop->until(fn () => $source->fired !== []);

    expect($source->fired)->toHaveCount(1)
        ->and($source->fired[0])->toBeInstanceOf(Readable::class);
});

it('runs deferred work on the next turn, and work it defers on the turn after', function () {
    $loop = loop();
    $log = [];

    $loop->resource('clock', pollable(function () use (&$log) { $log[] = 'tick'; }));
    $loop->defer(function () use ($loop, &$log) {
        $log[] = 'first';
        $loop->defer(function () use (&$log) { $log[] = 'second'; });
    });

    $loop->until(function () use (&$log) { return in_array('second', $log, true); });

    expect($log)->toBe(['tick', 'first', 'tick', 'second']);
});

it('makes the wait a glance while follow-on work is pending', function () {
    $loop = loop(pace_ms: 200);
    $done = false;

    $loop->resource('slow', pollable(fn () => null));
    $loop->defer(function () use (&$done) { $done = true; });

    $started = hrtime(true);
    $loop->until(function () use (&$done) { return $done; });

    expect(hrtime(true) - $started)->toBeLessThan(50_000_000);
});

it('passes the sleep to the next sleeper in line when the holder leaves', function () {
    $loop = loop();
    $slept = [];

    $loop->resource('a', sleeper('a', $slept));
    $loop->resource('b', sleeper('b', $slept));

    $loop->until(function () use (&$slept) { return count($slept) >= 1; });
    $loop->forget('a');
    $loop->until(function () use (&$slept) { return count($slept) >= 2; });

    expect($slept)->toBe(['a', 'b']);
});

it('lets a crowned sleeper take the sleep ahead of the line', function () {
    $loop = loop();
    $slept = [];

    $loop->resource('a', sleeper('a', $slept));
    $loop->resource('b', sleeper('b', $slept));
    $loop->crown('b');

    $loop->until(function () use (&$slept) { return count($slept) >= 1; });

    expect($slept)->toBe(['b']);
});

it('gives up with an exception when nothing left could make the condition true', function () {
    expect(fn () => loop()->until(fn () => false))
        ->toThrow(IOPoolsException::class, 'until() ran out of work before its condition was met.');
});

it('relays a signal to its watcher under the select backend', function () {
    $loop = loop();
    $source = wakeSource([new ControlSignal(SIGUSR1)]);

    $loop->resource('signals', $source);
    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGUSR1));
    $loop->until(fn () => $source->fired !== []);

    expect($source->fired[0])->toBeInstanceOf(ControlSignal::class)
        ->and($source->fired[0]->signo)->toBe(SIGUSR1);
})->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('settles a promise from a timer and hands wait() the value', function () {
    $loop = loop();
    $promise = $loop->promise();

    $loop->at(0.01, fn () => $promise->resolve(42));

    expect($promise->wait())->toBe(42);
});

it('throws the rejection reason out of wait()', function () {
    $loop = loop();
    $promise = $loop->promise();

    $loop->at(0.01, fn () => $promise->reject(new RuntimeException('no')));

    expect(fn () => $promise->wait())->toThrow(RuntimeException::class, 'no');
});

it('suspends an async body at await() and finishes it once the promise settles', function () {
    $loop = loop();

    $task = $loop->async(function () use ($loop) {
        $promise = $loop->promise();
        $loop->at(0.01, fn () => $promise->resolve('done'));

        return $loop->await($promise);
    });

    expect($task->wait())->toBe('done');
});

it('resumes a fiber whose promise settles in the last flush instead of cancelling it', function () {
    $loop = loop();
    $source = $loop->promise();
    // A chained promise settles only when the engine's queue is flushed.
    $chained = $source->then(fn (int $value): int => $value * 2);
    $task = $loop->async(fn () => $loop->await($chained));
    $source->resolve(21);

    $loop->until(fn () => $task->settled());

    expect($task->wait())->toBe(42);
});

it('lets run() resume a fiber whose promise settles in the last flush instead of cancelling it', function () {
    $loop = loop();
    $source = $loop->promise();
    $chained = $source->then(fn (int $value): int => $value * 2);
    $task = $loop->async(fn () => $loop->await($chained));
    $source->resolve(21);

    $loop->run();

    expect($task->wait())->toBe(42);
});

it('hands out its waiter backend\'s descriptor', function () {
    $registry = new ResourceRegistry();
    $backend = new StreamSelectWaiterBackend();
    $waiter = new LoopWaiter($registry, $backend, 5_000_000);
    $loop = new EventLoop($registry, $waiter, new GuzzlePromiseEngine());

    expect($waiter->descriptor())->toBeNull()
        ->and($loop->descriptor())->toBeNull();

    if (extension_loaded('kqueue')) {
        $registry = new ResourceRegistry();
        $kqueue = new Voyager\IOPools\Waiter\KqueueWaiterBackend();
        $loop = new EventLoop($registry, new LoopWaiter($registry, $kqueue, 5_000_000), new GuzzlePromiseEngine());

        expect($loop->descriptor())->toBe($kqueue->descriptor());
    }
});
