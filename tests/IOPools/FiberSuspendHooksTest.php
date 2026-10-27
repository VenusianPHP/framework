<?php

use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Contracts\IOPools\CancelledException;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->loop = $this->app->get('event-loop');
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** A task that waits on the next loop turn once, then returns $value. */
function awaitingTask($loop, mixed $value = null, ?Closure $before = null, ?Closure $after = null)
{
    return $loop->async(function () use ($loop, $value, $before, $after) {
        $before && $before();
        $loop->await($loop->defer(fn () => null));
        $after && $after();

        return $value;
    });
}

it('runs the suspending hooks in order and the resumed ones in reverse, in the waiting fiber', function () {
    $heard = [];

    foreach (['a', 'b'] as $name) {
        $this->loop->onFiberSuspend(
            function (Fiber $fiber) use (&$heard, $name) { $heard[] = ["suspend {$name}", $fiber === Fiber::getCurrent()]; },
            function (Fiber $fiber) use (&$heard, $name) { $heard[] = ["resume {$name}", $fiber === Fiber::getCurrent()]; },
        );
    }

    expect($this->loop->await(awaitingTask($this->loop, 'done')))->toBe('done')
        ->and($heard)->toBe([['suspend a', true], ['suspend b', true], ['resume b', true], ['resume a', true]]);
});

it('runs the resumed hooks when a waiting task is cancelled', function () {
    $heard = [];
    $this->loop->onFiberSuspend(function () use (&$heard) { $heard[] = 'suspend'; }, function () use (&$heard) { $heard[] = 'resume'; });

    $task = awaitingTask($this->loop);
    $task->cancel();

    expect(fn () => $this->loop->await($task))->toThrow(CancelledException::class)
        ->and($heard)->toBe(['suspend', 'resume']);
});

it('stops a suspend whose suspending hook throws, resuming the hooks that ran', function () {
    $heard = [];
    $this->loop->onFiberSuspend(function () use (&$heard) { $heard[] = 'suspend a'; }, function () use (&$heard) { $heard[] = 'resume a'; });
    $this->loop->onFiberSuspend(fn () => throw new LogicException('no'), function () use (&$heard) { $heard[] = 'resume b'; });
    $this->loop->onFiberSuspend(function () use (&$heard) { $heard[] = 'suspend c'; }, function () use (&$heard) { $heard[] = 'resume c'; });

    $after = false;
    $task = awaitingTask($this->loop, after: function () use (&$after) { $after = true; });

    expect(fn () => $this->loop->await($task))->toThrow(LogicException::class, 'no')
        ->and($heard)->toBe(['suspend a', 'resume a'])
        ->and($after)->toBeFalse();
});

it('keeps an exception from the suspend point, chaining a resumed hook\'s under it', function () {
    $this->loop->onFiberSuspend(fn () => null, fn () => throw new LogicException('hook failed'));

    $task = awaitingTask($this->loop);
    $task->cancel();

    try {
        $this->loop->await($task);
        $this->fail('the cancel was lost');
    } catch (CancelledException $e) {
        expect($e->getPrevious())->toBeInstanceOf(LogicException::class)
            ->and($e->getPrevious()->getMessage())->toBe('hook failed');
    }
});

it('throws a resumed hook\'s exception when nothing else was in flight, after the other hooks ran', function () {
    $heard = [];
    $this->loop->onFiberSuspend(fn () => null, function () use (&$heard) { $heard[] = 'resume a'; });
    $this->loop->onFiberSuspend(fn () => null, fn () => throw new LogicException('hook failed'));

    expect(fn () => $this->loop->await(awaitingTask($this->loop)))->toThrow(LogicException::class, 'hook failed')
        ->and($heard)->toBe(['resume a']);
});

/**
 * The pair a package would register to give each fiber its own output buffers: a fiber's base
 * level is taken when it starts; suspending lifts its buffers above that off the stack, resuming
 * puts them back.
 */
function bufferPerFiber($loop): void
{
    $base = new WeakMap;
    $parked = new WeakMap;

    $loop->onFiberStart(function (Fiber $fiber) use ($base) {
        $base[$fiber] = ob_get_level();
    });

    $loop->onFiberSuspend(
        function (Fiber $fiber) use ($base, $parked) {
            $held = [];

            while (ob_get_level() > ($base[$fiber] ?? ob_get_level())) {
                array_unshift($held, ob_get_clean());
            }

            $parked[$fiber] = $held;
        },
        function (Fiber $fiber) use ($parked) {
            foreach ($parked[$fiber] ?? [] as $contents) {
                ob_start();
                echo $contents;
            }

            unset($parked[$fiber]);
        },
    );
}

/** A task that echoes into a buffer of its own across a wait, returning what the buffer held. */
function echoingTask($loop, string $name, bool $nested = false)
{
    return $loop->async(function () use ($loop, $name, $nested) {
        ob_start();
        echo "{$name}1 ";

        if ($nested) {
            ob_start();
            echo "{$name}-inner ";
        }

        $loop->await($loop->defer(fn () => null));
        echo "{$name}2";

        $inner = $nested ? ob_get_clean() : '';

        return [ob_get_clean(), $inner];
    });
}

it('gives each fiber its own output buffers across a wait, with the hooks', function () {
    bufferPerFiber($this->loop);

    $a = echoingTask($this->loop, 'a');
    $b = echoingTask($this->loop, 'b', nested: true);

    expect($this->loop->await($a))->toBe(['a1 a2', ''])
        ->and($this->loop->await($b))->toBe(['b1 ', 'b-inner b2']);
});

it('mixes two fibers\' output across a wait without them', function () {
    $a = echoingTask($this->loop, 'a');
    $b = echoingTask($this->loop, 'b');

    expect([$this->loop->await($a)[0], $this->loop->await($b)[0]])->not->toBe(['a1 a2', 'b1 b2']);
});
