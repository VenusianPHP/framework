<?php

use Voyager\Http\Client\Pool;
use Voyager\Http\Client\Response;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Http\Async\LoopCurlHandler;
use Voyager\Http\Async\LoopPcurlHandler;
use Voyager\Http\Client\ConnectionException;
use Venusian\Tests\Http\Fixtures\HttpApp;
use Venusian\Tests\Http\Fixtures\SocketPairWatch;

dataset('drivers', function () {
    yield 'curl' => ['curl'];

    if (extension_loaded('pcurl')) {
        yield 'pcurl' => ['pcurl'];
    }
});

afterEach(fn () => HttpApp::tearDown($this));
afterAll(fn () => HttpApp::stopServer());

function http(string $driver): object
{
    $app = HttpApp::boot($driver);

    return (object) ['app' => $app, 'http' => $app->get('http'), 'loop' => $app->get(Loop::class)];
}

it('sends a blocking request without the loop', function (string $driver) {
    $t = http($driver);

    $response = $t->http->post(HttpApp::url('/echo?x=1'), ['name' => 'venusian']);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->json('method'))->toBe('POST')
        ->and($response->json('query'))->toBe(['x' => '1'])
        ->and(json_decode($response->json('body'), true))->toBe(['name' => 'venusian']);
})->with('drivers');

it('picks pcurl for auto when it is loaded, curl otherwise', function () {
    $t = http('auto');

    expect($t->app->get('http.async')->driver())
        ->toBeInstanceOf(extension_loaded('pcurl') ? LoopPcurlHandler::class : LoopCurlHandler::class);
});

it('returns a loop promise for an async request and settles it with the response', function (string $driver) {
    $t = http($driver);

    $promise = $t->http->async()->get(HttpApp::url('/echo?async=yes'));

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($promise->wait()->json('query'))->toBe(['async' => 'yes']);
})->with('drivers');

it('runs async requests at the same time', function (string $driver) {
    $t = http($driver);
    $started = hrtime(true);

    $promises = array_map(fn () => $t->http->async()->get(HttpApp::url('/delay?ms=300')), range(1, 5));
    $responses = array_map(fn (Promise $promise) => $promise->wait(), $promises);

    expect(array_map(fn (Response $response) => $response->json('delayed'), $responses))->toBe([300, 300, 300, 300, 300])
        ->and(hrtime(true) - $started)->toBeLessThan(900_000_000);
})->with('drivers');

it('sends a pool concurrently and keys its responses', function (string $driver) {
    $t = http($driver);
    $started = hrtime(true);

    $responses = $t->http->pool(fn (Pool $pool) => [
        $pool->as('first')->get(HttpApp::url('/delay?ms=300')),
        $pool->as('second')->get(HttpApp::url('/delay?ms=300')),
        $pool->as('third')->get(HttpApp::url('/delay?ms=300')),
    ]);

    expect(array_keys($responses))->toBe(['first', 'second', 'third'])
        ->and($responses['second']->json('delayed'))->toBe(300)
        ->and(hrtime(true) - $started)->toBeLessThan(700_000_000);
})->with('drivers');

it('holds a request back for its delay option', function (string $driver) {
    $t = http($driver);
    $started = hrtime(true);

    $t->http->async()->withOptions(['delay' => 250])->get(HttpApp::url('/echo'))->wait();

    expect(hrtime(true) - $started)->toBeGreaterThan(250_000_000);
})->with('drivers');

it('retries an async request until it succeeds', function (string $driver) {
    $t = http($driver);
    $key = bin2hex(random_bytes(4));

    $response = $t->http->retry(3, 0, throw: false)->async()->get(HttpApp::url("/flaky?key={$key}&fail=2"))->wait();

    expect($response->status())->toBe(200)
        ->and($response->json('attempt'))->toBe(3);
})->with('drivers');

it('rejects an async request whose connection fails', function (string $driver) {
    $t = http($driver);

    expect(fn () => $t->http->async()->get(HttpApp::closedPortUrl())->wait())
        ->toThrow(ConnectionException::class);
})->with('drivers');

it('lets run() end once its transfers finish', function (string $driver) {
    $t = http($driver);

    $promise = $t->http->async()->get(HttpApp::url('/delay?ms=100'));

    expect($t->loop->run())->toBe(0)
        ->and($promise->settled())->toBeTrue()
        ->and($promise->wait()->json('delayed'))->toBe(100);
})->with('drivers');

it('sends one request after another over a kept-alive connection', function (string $driver) {
    $t = http($driver);

    // curl lets go of the socket between requests and takes it back for the next one.
    $answers = [];
    foreach (range(1, 3) as $n) {
        $answers[] = $t->http->async()->get(HttpApp::url("/keep/echo?n={$n}"))->wait()->json('query');
    }

    expect($answers)->toBe([['n' => '1'], ['n' => '2'], ['n' => '3']]);
})->with('drivers');

it('closes the streams of released sockets once it has no transfers left', function () {
    $t = http('pcurl');

    $t->http->async()->get(HttpApp::url('/keep/echo?n=1'))->wait();
    $t->loop->run();

    $handler = $t->app->get('http.async')->driver();
    $held = fn (string $property): array => (fn () => $this->{$property})->call($handler);

    expect([...$held('retired'), ...$held('closing')])->toBe([]);
})->skip(! extension_loaded('pcurl'), 'needs ext-pcurl');

it('wakes another wake source on time while a slow response is in flight', function () {
    $t = http('pcurl');
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $watch = new SocketPairWatch($ours);
    $written_at = null;

    $t->loop->resource('watch', $watch);
    $slow = $t->http->async()->get(HttpApp::url('/delay?ms=600'));
    $t->loop->at(0.1, function () use ($theirs, &$written_at) {
        fwrite($theirs, 'ping');
        $written_at = hrtime(true);
    });

    $slow->wait();

    expect($watch->woke_at)->not->toBeNull()
        ->and($watch->woke_at - $written_at)->toBeLessThan(10_000_000);
})->skip(! extension_loaded('pcurl'), 'needs ext-pcurl');
