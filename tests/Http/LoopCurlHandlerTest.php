<?php

use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Psr7\Request;
use Venusian\Tests\Http\Support\LocalHttpServer;
use Voyager\Http\Async\LoopCurlHandler;
use Voyager\IOPools\EventLoop;

beforeEach(function () {
    $this->server = new LocalHttpServer;
    $this->loop = new EventLoop;
    $this->handler = new LoopCurlHandler($this->loop, new CurlFactory(10));
});
afterEach(fn () => $this->server->stop());

it('resolves a request on the loop and leaves when idle', function () {
    $promise = ($this->handler)(new Request('GET', $this->server->url('/ok')), []);
    expect($this->handler->inFlight())->toBe(1);

    $response = $this->loop->adopt($promise)->wait();

    expect($response->getStatusCode())->toBe(200)
        ->and($this->handler->inFlight())->toBe(0);
});

it('runs two slow requests concurrently', function () {
    $t = microtime(true);
    $a = ($this->handler)(new Request('GET', $this->server->url('/slow?ms=300')), []);
    $b = ($this->handler)(new Request('GET', $this->server->url('/slow?ms=300')), []);
    $this->loop->adopt($a)->wait();
    $this->loop->adopt($b)->wait();
    expect(microtime(true) - $t)->toBeLessThan(0.55);
});

it('rejects a refused connection', function () {
    $p = ($this->handler)(new Request('GET', 'http://127.0.0.1:1/'), []);
    expect(fn () => $this->loop->adopt($p)->wait())->toThrow(\GuzzleHttp\Exception\ConnectException::class);
});

it('run() exits by itself once responses land', function () {
    $p = ($this->handler)(new Request('GET', $this->server->url('/ok')), []);
    $guard = $this->loop->at(2, fn () => $this->loop->stop(1));
    $p->then(fn () => $guard->cancel());
    expect($this->loop->run())->toBe(0)
        ->and($this->handler->inFlight())->toBe(0);
});

it('forget clears the sleeper slot', function () {
    $p = ($this->handler)(new Request('GET', $this->server->url('/ok')), []);
    $this->loop->adopt($p)->wait();
    // pins today's notebook: the sleeper slot is null after forget, a displaced sleeper is not restored
    $notebook = (fn () => $this->notebook)->call($this->loop);
    expect((fn () => $this->fallback_timer_owner)->call($notebook))->toBeNull();
});
