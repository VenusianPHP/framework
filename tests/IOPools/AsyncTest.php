<?php

use Venusian\Tests\IOPools\Fixtures\TestEvent;
use Voyager\Contracts\IOPools\CancelledException;
use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Contracts\IOPools\Task;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\PromiseEngines\ReactPromiseEngine;

dataset('async engines', [
    'guzzle' => [fn () => new GuzzlePromiseEngine],
    'react'  => [fn () => new ReactPromiseEngine],
]);

it('hands back the body\'s return through wait()', function ($engine) {
    $loop = new EventLoop(promise_engine: $engine);

    $task = $loop->async(fn () => 5);

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->wait())->toBe(5);
})->with('async engines');

it('runs the body to its first wait before async() returns', function () {
    $loop = new EventLoop;
    $steps = [];

    $loop->async(function () use ($loop, &$steps) {
        $steps[] = 'started';
        $loop->promise()->wait();
    });
    $steps[] = 'returned';

    expect($steps)->toBe(['started', 'returned']);
});

it('lets two waits overlap instead of running back to back', function ($engine) {
    $loop = new EventLoop(promise_engine: $engine);
    $start = microtime(true);

    $a = $loop->async(function () use ($loop) {
        $p = $loop->promise(); $loop->at(0.05, fn () => $p->resolve('a')); return $p->wait();
    });
    $b = $loop->async(function () use ($loop) {
        $p = $loop->promise(); $loop->at(0.05, fn () => $p->resolve('b')); return $p->wait();
    });

    expect($a->wait().$b->wait())->toBe('ab')
        ->and(microtime(true) - $start)->toBeLessThan(0.09);       // serial would be ≥ 0.10
})->with('async engines');

it('resumes in the turn the wait settles, not a fallback pace later', function () {
    $loop = new EventLoop(tick_budget_ms: 200);                     // make a late resume obvious
    $resolved_at = $resumed_at = null;

    $task = $loop->async(function () use ($loop, &$resumed_at, &$resolved_at) {
        $p = $loop->promise();
        $loop->at(0.01, function () use ($p, &$resolved_at) { $resolved_at = microtime(true); $p->resolve(1); });
        $p->wait();
        $resumed_at = microtime(true);
    });
    $task->wait();

    expect($resumed_at - $resolved_at)->toBeLessThan(0.02);
});

it('keeps delivering mail while a fiber waits', function () {
    $handler = new class implements Receivable {
        public ?float $delivered_at = null;
        public function handOff(MailCollection $mail): void { $this->delivered_at = microtime(true); }
    };
    $loop = new EventLoop(mail_handler: $handler);
    $finished_at = null;

    $loop->async(function () use ($loop, &$finished_at) {
        $p = $loop->promise();
        $loop->at(0.08, fn () => $p->resolve(1));
        $p->wait();
        $finished_at = microtime(true);
    });
    $loop->at(0.01, fn () => $loop->post(new TestEvent('while-waiting')));

    $loop->run();

    expect($handler->delivered_at)->not->toBeNull()
        ->and($handler->delivered_at)->toBeLessThan($finished_at);
});

it('rejects the task when the body throws, and the run survives', function () {
    $loop = new EventLoop;
    $fired = false;

    $task = $loop->async(fn () => throw new RuntimeException('bad body'));
    $loop->at(0.01, function () use (&$fired) { $fired = true; });

    expect($loop->run())->toBe(0)
        ->and($fired)->toBeTrue()
        ->and(fn () => $task->wait())->toThrow(RuntimeException::class, 'bad body');
});

it('cancels fibers that can never wake when run() runs out of work', function () {
    $loop = new EventLoop;

    $task = $loop->async(fn () => $loop->promise()->wait());          // nobody will resolve it

    $start = microtime(true);
    $loop->run();

    expect(microtime(true) - $start)->toBeLessThan(0.1)
        ->and($task->rejected())->toBeTrue()
        ->and(fn () => $task->wait())->toThrow(CancelledException::class);
});

it('cancels a stuck fiber when wait() on the main stack runs out of work', function () {
    $loop = new EventLoop;

    $task = $loop->async(fn () => $loop->promise()->wait());

    expect(fn () => $task->wait())->toThrow(CancelledException::class);
});

it('cancels suspended fibers when the loop is stopped', function () {
    $loop = new EventLoop;
    $cleaned = false;

    $task = $loop->async(function () use ($loop, &$cleaned) {
        try { $loop->promise()->wait(); } finally { $cleaned = true; }
    });
    $loop->at(0.01, fn () => $loop->stop(3));

    expect($loop->run())->toBe(3)
        ->and($cleaned)->toBeTrue()
        ->and(fn () => $task->wait())->toThrow(CancelledException::class);
});

it('cancel() from outside rejects the task and runs the body\'s finally', function () {
    $loop = new EventLoop;
    $cleaned = false;

    $task = $loop->async(function () use ($loop, &$cleaned) {
        try { $loop->promise()->wait(); } finally { $cleaned = true; }
    });
    $task->cancel();

    expect($cleaned)->toBeTrue()
        ->and(fn () => $task->wait())->toThrow(CancelledException::class);
});

it('nests: an async body may start and wait on another', function () {
    $loop = new EventLoop;

    $outer = $loop->async(function () use ($loop) {
        $inner = $loop->async(function () use ($loop) {
            $p = $loop->promise(); $loop->at(0.01, fn () => $p->resolve(2)); return $p->wait();
        });
        return $inner->wait() * 10;
    });

    expect($outer->wait())->toBe(20);
});

it('borrows, not suspends, inside a fiber the loop did not start', function () {
    $loop = new EventLoop;
    $p = $loop->promise();
    $loop->at(0.01, fn () => $p->resolve('borrowed'));

    $fiber = new Fiber(fn () => $p->wait());
    $fiber->start();

    expect($fiber->isTerminated())->toBeTrue()                       // it never suspended: it turned the loop itself
        ->and($fiber->getReturn())->toBe('borrowed');
});

it('chains like a promise', function () {
    $loop = new EventLoop;

    $doubled = $loop->async(fn () => 21)->then(fn (int $n) => $n * 2);

    expect($doubled->wait())->toBe(42);
});
