<?php

use Venusian\Tests\Http\Support\LocalHttpServer;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Http\Async\HttpAsyncManager;
use Voyager\Http\Async\LoopCurlHandler;
use Voyager\Http\Async\LoopPcurlHandler;
use Voyager\Http\Client\ConnectionException;
use Voyager\Http\Client\Factory;
use Voyager\Http\Client\Pool;

require_once __DIR__.'/Support/helpers.php';

beforeEach(function () {
    $this->server = new LocalHttpServer;
    $this->vessel = httpVessel();
    $this->loop = $this->vessel['event-loop'];
    $this->http = new Factory(null, new HttpAsyncManager($this->vessel));
});
afterEach(fn () => $this->server->stop());

it('async send returns a loop promise', function () {
    $p = $this->http->async()->get($this->server->url('/ok'));
    expect($p)->toBeInstanceOf(Promise::class)
        ->and($p->wait()->json('ok'))->toBeTrue();
});

it('sync send never touches the driver', function () {
    $this->http->get($this->server->url('/ok'));
    expect($this->http->loopHandler()->inFlight())->toBe(0)
        ->and((fn () => $this->registered)->call($this->http->loopHandler()))->toBeFalse();
});

it('fake never registers', function () {
    $this->http->fake(['*' => Factory::response('x', 200)]);
    expect($this->http->async()->get('http://nowhere.test/')->wait()->body())->toBe('x')
        ->and((fn () => $this->registered)->call($this->http->loopHandler()))->toBeFalse();
});

it('refused rejects with ConnectionException', function () {
    expect(fn () => $this->http->async()->get('http://127.0.0.1:1/')->wait())->toThrow(ConnectionException::class);
});

it('pool and batch use the loop handler and keep order', function () {
    $r = $this->http->pool(fn (Pool $pool) => [
        $pool->as('a')->get($this->server->url('/slow?ms=200')),
        $pool->as('b')->get($this->server->url('/ok')),
    ]);
    expect(array_keys($r))->toBe(['a', 'b'])
        ->and($r['a']->json('slept'))->toBe(200)
        ->and((fn () => $this->handler)->call(new Pool($this->http)))->toBeInstanceOf(
            (getenv('HTTP_ASYNC_DRIVER') ?: 'curl') === 'pcurl' ? LoopPcurlHandler::class : LoopCurlHandler::class,
        );

    $b = $this->http->batch(fn ($batch) => [$batch->get($this->server->url('/ok')), $batch->get($this->server->url('/status/204'))])->send();
    expect($b[0]->status())->toBe(200)->and($b[1]->status())->toBe(204);
});

it('pool honours its concurrency cap on the loop', function () {
    $t = microtime(true);
    $r = $this->http->pool(fn (Pool $pool) => [
        $pool->get($this->server->url('/slow?ms=200')),
        $pool->get($this->server->url('/slow?ms=200')),
    ], concurrency: 1);
    expect($r[0]->ok() && $r[1]->ok())->toBeTrue()
        ->and(microtime(true) - $t)->toBeGreaterThan(0.39);
});

it('pool without loop falls back to Guzzle', function () {
    $http = new Factory;
    $r = $http->pool(fn (Pool $pool) => [$pool->get($this->server->url('/ok'))]);
    expect($r[0]->ok())->toBeTrue()
        ->and((fn () => $this->handler)->call(new Pool($http)))->not->toBeInstanceOf(LoopCurlHandler::class);
});
