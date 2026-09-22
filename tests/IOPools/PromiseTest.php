<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\Contracts\IOPools\PromiseEngine;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\Promise;
use Voyager\IOPools\PromiseEngineManager;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\PromiseEngines\ReactPromiseEngine;
use Voyager\Vessel\ControlPanel;

dataset('engines', [
    'guzzle' => [fn () => new GuzzlePromiseEngine],
    'react'  => [fn () => new ReactPromiseEngine],
]);

function loopOn(PromiseEngine $engine): EventLoop
{
    return new EventLoop(promise_engine: $engine);
}

it('waits for a promise resolved later by the loop', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $promise = $loop->promise();

    $loop->at(0.01, fn () => $promise->resolve('answer'));

    expect($promise->settled())->toBeFalse()
        ->and($promise->wait())->toBe('answer')
        ->and($promise->fulfilled())->toBeTrue();
})->with('engines');

it('throws the reason out of wait() when rejected', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $promise = $loop->promise();

    $loop->at(0.01, fn () => $promise->reject(new RuntimeException('nope')));

    expect(fn () => $promise->wait())->toThrow(RuntimeException::class, 'nope')
        ->and($promise->rejected())->toBeTrue();
})->with('engines');

it('waits on a promise that was already settled, with nothing left on the loop', function (PromiseEngine $engine) {
    $promise = loopOn($engine)->promise();

    $promise->resolve(42);

    expect($promise->wait())->toBe(42);
})->with('engines');

it('chains then(), each link getting the last one\'s return', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $promise = $loop->promise();

    $chained = $promise
        ->then(fn (int $n) => $n + 1)
        ->then(fn (int $n) => $n * 10);

    $loop->at(0.005, fn () => $promise->resolve(1));

    expect($chained->wait())->toBe(20);
})->with('engines');

it('recovers through error(), and skips it when nothing failed', function (PromiseEngine $engine) {
    $loop = loopOn($engine);

    $failed = $loop->promise();
    $fine = $loop->promise();

    $recovered = $failed->error(fn (Throwable $e) => 'recovered from '.$e->getMessage());
    $untouched = $fine->error(fn () => 'should not run');

    $failed->reject(new RuntimeException('boom'));
    $fine->resolve('fine');

    expect($recovered->wait())->toBe('recovered from boom')
        ->and($untouched->wait())->toBe('fine');
})->with('engines');

it('runs finally() either way and passes the outcome through', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $ran = 0;

    $good = $loop->promise();
    $bad = $loop->promise();

    $after_good = $good->finally(function () use (&$ran) { $ran++; });
    $after_bad = $bad->finally(function () use (&$ran) { $ran++; });

    $good->resolve('kept');
    $bad->reject(new RuntimeException('kept too'));

    expect($after_good->wait())->toBe('kept')
        ->and(fn () => $after_bad->wait())->toThrow(RuntimeException::class, 'kept too')
        ->and($ran)->toBe(2);
})->with('engines');

it('runs then() callbacks during a plain run(), nobody waiting', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $promise = $loop->promise();
    $seen = null;

    $promise->then(function ($value) use (&$seen) { $seen = $value; });

    $loop->at(0.005, fn () => $promise->resolve('heard'));
    $loop->run();

    expect($seen)->toBe('heard');
})->with('engines');

it('lets two promises be in flight at once and keeps their answers apart', function (PromiseEngine $engine) {
    $loop = loopOn($engine);

    $slow = $loop->promise();
    $fast = $loop->promise();

    $loop->at(0.02, fn () => $slow->resolve('slow'));
    $loop->at(0.005, fn () => $fast->resolve('fast'));

    $start = microtime(true);

    expect($slow->wait())->toBe('slow')
        ->and($fast->settled())->toBeTrue()          // landed while we waited on the other
        ->and($fast->wait())->toBe('fast')
        ->and(microtime(true) - $start)->toBeLessThan(0.06);
})->with('engines');

it('throws out of wait() when nothing on the loop could ever settle it', function (PromiseEngine $engine) {
    $promise = loopOn($engine)->promise();

    expect(fn () => $promise->wait())->toThrow(EventLoopException::class, 'ran out of work');
})->with('engines');

it('awaits our promise, a foreign thenable, and a plain value alike', function (PromiseEngine $engine) {
    $loop = loopOn($engine);

    $ours = $loop->promise();
    $react = new React\Promise\Deferred;
    $guzzle = new GuzzleHttp\Promise\Promise;

    $loop->at(0.005, function () use ($ours, $react, $guzzle) {
        $ours->resolve('ours');
        $react->resolve('react');
        $guzzle->resolve('guzzle');
    });

    expect($loop->await($ours))->toBe('ours')
        ->and($loop->await($react->promise()))->toBe('react')
        ->and($loop->await($guzzle))->toBe('guzzle')
        ->and($loop->await('plain'))->toBe('plain');
})->with('engines');

it('adopts a foreign thenable without blocking', function (PromiseEngine $engine) {
    $loop = loopOn($engine);
    $guzzle = new GuzzleHttp\Promise\Promise;
    $adopted = $loop->adopt($guzzle);

    expect($adopted->settled())->toBeFalse();
    $loop->at(0.01, fn () => $guzzle->resolve('answer'));
    expect($adopted->wait())->toBe('answer');
})->with('engines');

it('refuses to settle a chained promise on the react engine', function () {
    $chained = loopOn(new ReactPromiseEngine)->promise()->then(fn ($v) => $v);

    expect(fn () => $chained->resolve('x'))->toThrow(EventLoopException::class, 'chained one settles itself');
});

it('builds the configured engine, and throws when its package is missing', function () {
    $vessel = new ControlPanel;
    $vessel->registerInstance('config', new Repository);

    $manager = new class($vessel) extends PromiseEngineManager {
        public function getDefaultDriver(): string { return 'guzzle'; }

        public function createGhostDriver(): PromiseEngine
        {
            $this->ensureInstalled('Ghost\\Promise\\NotInstalled', 'ghost', 'ghost/promise');

            return new GuzzlePromiseEngine;
        }
    };

    expect($manager->driver())->toBeInstanceOf(GuzzlePromiseEngine::class)
        ->and($manager->driver('react'))->toBeInstanceOf(ReactPromiseEngine::class)
        ->and(fn () => $manager->driver('ghost'))
            ->toThrow(EventLoopException::class, 'composer require ghost/promise');
});
