<?php

use Voyager\Redis\RedisManager;
use Voyager\Redis\RedisMessage;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Redis\Sockets\RedisSocketException;
use Venusian\Tests\Redis\Fixtures\RedisApp;

beforeEach(function () {
    RedisApp::reachable() || $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    $this->app = RedisApp::boot();
    $this->redis = $this->app->get('redis');
    $this->loop = $this->app->get(Loop::class);
    $this->heard = [];
});
afterEach(fn () => isset($this->app) && RedisApp::tearDown($this->app, $this));

/** Runs the loop until $count values of $key have been dispatched as mail, or five seconds pass. */
function popMail(object $test, string $key, int $count): array
{
    $test->app->get('signals')->listen("redis:{$key}", function (RedisMessage $message) use ($test, $count) {
        $test->heard[] = $message->raw;

        if (count($test->heard) === $count) {
            $test->loop->stop();
        }
    });

    $test->loop->at(5, fn () => $test->loop->stop());
    $test->loop->run();

    return $test->heard;
}

it('pops pushed values as mail, in the order they were pushed', function () {
    $this->loop->resource('jobs', $this->redis->listPop('jobs'));
    $push = $this->redis->listPush('jobs');

    foreach (range(1, 5) as $n) {
        $push->push("job {$n}");
    }

    expect(popMail($this, 'jobs', 5))->toBe(['job 1', 'job 2', 'job 3', 'job 4', 'job 5']);
});

it('resolves a push with the list\'s length, then leaves the loop', function () {
    $push = $this->redis->listPush('lengths');

    expect($push->push('a', 'b')->wait())->toBe(2)
        ->and($push->push('c')->wait())->toBe(3)
        ->and($this->loop->run())->toBe(0)
        ->and($this->redis->connection()->lrange('lengths', 0, -1))->toBe(['a', 'b', 'c']);
});

it('drains a backlog pushed through the blocking connection, with its prefix', function () {
    $values = array_map(fn (int $n): string => "backlog {$n}", range(1, 200));
    $this->redis->connection()->rpush('backlog', ...$values);

    $this->loop->resource('backlog', $this->redis->listPop('backlog', batch: 64));

    expect(popMail($this, 'backlog', 200))->toBe($values)
        ->and($this->redis->connection()->llen('backlog'))->toBe(0);
});

it('rejects a push Redis refuses', function () {
    $this->redis->connection()->set('scalar', 'not a list');

    expect(fn () => $this->redis->listPush('scalar')->push('value')->wait())
        ->toThrow(RedisSocketException::class, 'RPUSH scalar failed: WRONGTYPE');
});

it('reconnects and keeps popping after the server drops its socket', function () {
    $this->loop->resource('survives', $this->redis->listPop('survives'));
    $blocking = $this->redis->connection();

    $this->loop->at(0.2, function () use ($blocking) {
        foreach (explode("\n", trim($blocking->client()->rawCommand('CLIENT', 'LIST'))) as $client) {
            if (str_contains($client, 'cmd=blpop')) {
                preg_match('/\bid=(\d+)/', $client, $id);
                $blocking->client()->rawCommand('CLIENT', 'KILL', 'ID', $id[1]);
            }
        }
    });
    $this->loop->at(0.4, fn () => $blocking->rpush('survives', 'after the drop'));

    expect(popMail($this, 'survives', 1))->toBe(['after the drop']);
});

it('keeps run() going while it waits for values', function () {
    $this->loop->resource('waiting', $this->redis->listPop('waiting'));
    $this->loop->at(0.3, fn () => $this->redis->connection()->rpush('waiting', 'late'));

    $started = hrtime(true);

    expect(popMail($this, 'waiting', 1))->toBe(['late'])
        ->and(hrtime(true) - $started)->toBeGreaterThan(250_000_000);
});

it('hands back what it popped but never delivered, at the head of the list, in order', function () {
    $pop = $this->redis->listPop('handback');
    $pop->wakes();                                   // arms the BLPOP without a loop turn
    $blocking = $this->redis->connection();

    $blocking->rpush('handback', 'first', 'second', 'third');
    usleep(100_000);                                 // Redis answers the BLPOP into our socket

    $pop->release();

    expect($blocking->lrange('handback', 0, -1))->toBe(['first', 'second', 'third'])
        ->and($pop->pump())->toBe([]);
});

it('hands its socket back when the loop stops', function () {
    $this->loop->resource('stopping', $this->redis->listPop('stopping'));
    $this->loop->at(0.1, fn () => $this->loop->stop());
    $this->loop->run();

    $blocking = $this->redis->connection();
    $blocking->rpush('stopping', 'kept');
    usleep(100_000);

    expect($blocking->lrange('stopping', 0, -1))->toBe(['kept'])
        ->and(str_contains($blocking->client()->rawCommand('CLIENT', 'LIST'), 'cmd=blpop'))->toBeFalse();
});

it('leaves the connection free for blocking commands while a pop waits', function () {
    $pop = $this->redis->listPop('beside');
    $pop->wakes();

    $blocking = $this->redis->connection();
    $blocking->set('beside-key', 'still answers');

    expect($blocking->get('beside-key'))->toBe('still answers');

    $pop->release();
});

it('refuses a cluster connection and a batch under one', function () {
    $redis = new RedisManager($this->app, 'phpredis', [
        'default' => ['host' => '127.0.0.1', 'port' => 6379],
        'clusters' => ['ring' => [['host' => '127.0.0.1', 'port' => 6379]]],
    ]);

    expect(fn () => $redis->listPop('nope', 'ring'))
        ->toThrow(InvalidArgumentException::class, 'Redis list resources need a single-node connection, and [ring] is a cluster.')
        ->and(fn () => $redis->listPop('nope', batch: 0))
        ->toThrow(InvalidArgumentException::class, 'A list pop takes at least one value per LPOP, 0 given.');
});
