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
        public array $moving = [];
        public function progress(): array
        {
            return $this->moving;
        }
    };
}

test('progress rides its own event lane and hook, and only when the bytes move', function () {
    $driver = fakeDriver();
    $queue = new EventQueue();
    $pool = new HttpPool($driver, $queue);

    $seen = [];
    $pool->call('clip', 'get', 'https://x/clip.mp4')
        ->onProgress(function (int $now, int $total) use (&$seen) { $seen[] = [$now, $total]; });

    $driver->moving = ['clip' => ['now' => 1024, 'total' => 4096]];
    $pool->tick();

    $events = $queue->drain();
    expect($events->has('progress.clip'))->toBeTrue()
        ->and($events->get('progress.clip')->family)->toBe('task.progress')
        ->and($events->get('progress.clip')->payload)->toBe(['name' => 'clip', 'bytes_now' => 1024, 'bytes_total' => 4096])
        ->and($seen)->toBe([[1024, 4096]]);

    // Same bytes again: nothing new to say.
    $pool->tick();
    expect($queue->drain()->has('progress.clip'))->toBeFalse()
        ->and($seen)->toBe([[1024, 4096]]);

    // More bytes: the lane speaks again.
    $driver->moving = ['clip' => ['now' => 2048, 'total' => 4096]];
    $pool->tick();
    expect($queue->drain()->get('progress.clip')->payload['bytes_now'])->toBe(2048)
        ->and($seen)->toBe([[1024, 4096], [2048, 4096]]);
});

test('completion clears the progress bookkeeping with the name', function () {
    $driver = fakeDriver();
    $queue = new EventQueue();
    $pool = new HttpPool($driver, $queue);

    $pool->call('clip', 'get', 'https://x/clip.mp4');
    $driver->moving = ['clip' => ['now' => 4096, 'total' => 4096]];
    $pool->tick();
    $queue->drain();

    $driver->moving = [];
    $driver->ready[] = new HttpResult('clip', true, 200, [], 'bytes');
    $pool->tick();

    expect($queue->drain()->has('clip'))->toBeTrue();

    // The name is free again and a new call starts progress from scratch.
    $pool->call('clip', 'get', 'https://x/clip.mp4');
    $driver->moving = ['clip' => ['now' => 4096, 'total' => 4096]];
    $pool->tick();
    expect($queue->drain()->has('progress.clip'))->toBeTrue();
});

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

test('inFlight answers the pending call by name until it settles', function () {
    $driver = fakeDriver();
    $pool = new HttpPool($driver, new EventQueue());

    $call = $pool->call('meta', 'get', 'https://x/meta');

    expect($pool->inFlight('meta'))->toBe($call)
        ->and($pool->inFlight('ghost'))->toBeNull();

    $driver->ready[] = new HttpResult('meta', true, 200, [], '{}');
    $pool->tick();

    expect($pool->inFlight('meta'))->toBeNull();
});
