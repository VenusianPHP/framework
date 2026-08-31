<?php

use Voyager\Contracts\IOPools\HttpDriver;
use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\IOPools\EventQueue;
use Voyager\IOPools\HttpPool;
use Voyager\IOPools\HttpResult;

function fakeDriver(): HttpDriver
{
    return new class implements HttpDriver {
        public array $dispatched = [];
        public array $ready = [];
        public function dispatch(string $name, string $method, string $url, array $headers, ?string $body): void
        {
            $this->dispatched[] = compact('name', 'method', 'url', 'headers', 'body');
        }
        public function harvest(): array
        {
            $drained = $this->ready;
            $this->ready = [];
            return $drained;
        }
    };
}

test('dispatches with an uppercased method and delivers a raw-named task event', function () {
    $driver = fakeDriver();
    $queue = new EventQueue();
    $pool = new HttpPool($driver, $queue);

    $pool->call('api-somewhere', 'get', 'https://x', ['K' => 'v']);
    $driver->ready[] = new HttpResult('api-somewhere', true, 200, [], '{"ok":1}');
    $pool->tick();

    $event = $queue->drain()->get('api-somewhere');
    expect($driver->dispatched[0]['method'])->toBe('GET')
        ->and($event->family)->toBe('task')
        ->and($event->payload['status'])->toBe(200);
});

test('success and fail lanes follow transport truth, not HTTP status', function () {
    $driver = fakeDriver();
    $pool = new HttpPool($driver, new EventQueue());
    $lane = null;
    $pool->call('w', 'get', 'https://x')
        ->onSuccess(function (HttpResult $r) use (&$lane) { $lane = "success:{$r->status}"; })
        ->onFail(function (HttpResult $r) use (&$lane) { $lane = "fail:{$r->error}"; });

    $driver->ready[] = new HttpResult('w', true, 404, [], 'nope');
    $pool->tick();
    expect($lane)->toBe('success:404');

    $pool->call('w', 'get', 'https://x')
        ->onFail(function (HttpResult $r) use (&$lane) { $lane = "fail:{$r->error}"; });
    $driver->ready[] = new HttpResult('w', false, 0, [], '', 'timeout');
    $pool->tick();
    expect($lane)->toBe('fail:timeout');
});

test('refuses a duplicate in-flight name and frees it after settling', function () {
    $driver = fakeDriver();
    $pool = new HttpPool($driver, new EventQueue());
    $call = $pool->call('w', 'get', 'https://x');

    expect(fn () => $pool->call('w', 'get', 'https://x'))
        ->toThrow(IOPoolsException::class, 'already in flight');

    $driver->ready[] = new HttpResult('w', true, 200, [], '');
    $pool->tick();

    expect($call->settled())->toBeTrue()
        ->and($call->result()->status)->toBe(200)
        ->and($pool->call('w', 'get', 'https://x'))->not->toBeNull();
});
