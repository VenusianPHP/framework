<?php

use Voyager\Contracts\IOPools\CancelledException;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\FiberScheduler;

beforeEach(function () {
    $this->loop = new EventLoop;
    $this->scheduler = new FiberScheduler;
});

it('runs the body at start and resolves the promise with its return', function () {
    $promise = $this->loop->promise();

    $fiber = $this->scheduler->start(fn () => 5, $promise);

    expect($fiber->isTerminated())->toBeTrue()
        ->and($this->scheduler->owns($fiber))->toBeFalse()
        ->and($this->scheduler->idle())->toBeTrue()
        ->and($promise->wait())->toBe(5);
});

it('parks a fiber that suspends and resumes it once its assertion holds', function () {
    $promise = $this->loop->promise();
    $ready = false;

    $fiber = $this->scheduler->start(function () use (&$ready) {
        Fiber::suspend(function () use (&$ready) { return $ready; });
        return 'after';
    }, $promise);

    expect($fiber->isSuspended())->toBeTrue()
        ->and($this->scheduler->owns($fiber))->toBeTrue()
        ->and($this->scheduler->idle())->toBeFalse()
        ->and($this->scheduler->resume())->toBeFalse();       // not yet

    $ready = true;

    expect($this->scheduler->resume())->toBeTrue()
        ->and($fiber->isTerminated())->toBeTrue()
        ->and($this->scheduler->idle())->toBeTrue()
        ->and($promise->wait())->toBe('after');
});

it('rejects the promise when the body throws', function () {
    $promise = $this->loop->promise();

    $this->scheduler->start(fn () => throw new RuntimeException('bad body'), $promise);

    expect(fn () => $promise->wait())->toThrow(RuntimeException::class, 'bad body');
});

it('throws an assertion failure into the fiber instead of out of resume()', function () {
    $promise = $this->loop->promise();

    $this->scheduler->start(function () {
        try { Fiber::suspend(fn () => throw new LogicException('bad assertion')); }
        catch (LogicException $e) { return 'caught '.$e->getMessage(); }
    }, $promise);

    expect($this->scheduler->resume())->toBeTrue()
        ->and($promise->wait())->toBe('caught bad assertion');
});

it('cancel() lands as CancelledException at the suspend point', function () {
    $promise = $this->loop->promise();
    $finally_ran = false;

    $fiber = $this->scheduler->start(function () use (&$finally_ran) {
        try { Fiber::suspend(fn () => false); }
        finally { $finally_ran = true; }
    }, $promise);

    $this->scheduler->cancel($fiber);

    expect($finally_ran)->toBeTrue()
        ->and($this->scheduler->idle())->toBeTrue()
        ->and(fn () => $promise->wait())->toThrow(CancelledException::class);
});

it('a body may catch its cancellation and finish normally', function () {
    $promise = $this->loop->promise();

    $fiber = $this->scheduler->start(function () {
        try { Fiber::suspend(fn () => false); } catch (CancelledException) { return 'cleaned up'; }
    }, $promise);

    $this->scheduler->cancel($fiber);

    expect($promise->wait())->toBe('cleaned up');
});

it('cancel() on a finished fiber is a no-op', function () {
    $promise = $this->loop->promise();
    $fiber = $this->scheduler->start(fn () => 1, $promise);

    $this->scheduler->cancel($fiber);

    expect($promise->wait())->toBe(1);
});

it('cancelAll() empties the scheduler', function () {
    $a = $this->loop->promise();
    $b = $this->loop->promise();
    $this->scheduler->start(fn () => Fiber::suspend(fn () => false), $a);
    $this->scheduler->start(fn () => Fiber::suspend(fn () => false), $b);

    $this->scheduler->cancelAll();

    expect($this->scheduler->idle())->toBeTrue()
        ->and(fn () => $a->wait())->toThrow(CancelledException::class)
        ->and(fn () => $b->wait())->toThrow(CancelledException::class);
});

it('does not re-enter resume() from inside a resumed fiber', function () {
    $promise = $this->loop->promise();
    $inner = null;

    $this->scheduler->start(function () use (&$inner) {
        Fiber::suspend(fn () => true);
        $inner = $this->scheduler->resume();         // called while we are the fiber being resumed
    }, $promise);

    $this->scheduler->resume();

    expect($inner)->toBeFalse();
});
