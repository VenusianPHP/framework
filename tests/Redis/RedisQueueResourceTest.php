<?php

use Venusian\Tests\Concerns\InteractsWithRedis;
use Venusian\Tests\IOPools\Fixtures\TestEvent;
use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Contracts\IOPools\StreamWatchable;
use Voyager\IOPools\EventLoop;
use Voyager\Redis\RedisMessage;
use Voyager\Redis\RedisQueueResource;
use Voyager\Redis\PollingRedisQueueResource;
use Voyager\Redis\WatchedRedisQueueResource;

uses(InteractsWithRedis::class);

beforeEach(function () { $this->setUpRedis(); });
afterEach(function () { $this->tearDownRedis(); });

function recorder(): Receivable
{
    return new class implements Receivable {
        public array $mail = [];
        public function handOff(MailCollection $mail): void { $this->mail = [...$this->mail, ...$mail->mail()->all()]; }
    };
}

foreach (['phpredis' => PollingRedisQueueResource::class, 'predis' => WatchedRedisQueueResource::class] as $client => $class) {
    describe($client, function () use ($client, $class) {
        it('picks the resource shape from the client', function () use ($client, $class) {
            $resource = RedisQueueResource::make($this->redis[$client]->connection(), 'vf:test:'.$client);

            expect($resource)->toBeInstanceOf($class);
        });

        it('delivers a posted Event as mail, reconstituted', function () use ($client) {
            $handler = recorder();
            $loop = new EventLoop(mail_handler: $handler);
            $resource = RedisQueueResource::make($this->redis[$client]->connection(), 'vf:test:'.$client);
            $loop->resource('redis', $resource);

            $resource->post(new TestEvent('hello', ['n' => 1]));
            $loop->at(0.3, fn () => $loop->stop());
            $loop->run();

            expect($handler->mail)->toHaveCount(1)
                ->and($handler->mail[0])->toBeInstanceOf(TestEvent::class)
                ->and($handler->mail[0]->name())->toBe('hello')
                ->and($handler->mail[0]->payload)->toBe(['n' => 1]);
        });

        it('wraps a foreign payload as RedisMessage', function () use ($client) {
            $handler = recorder();
            $loop = new EventLoop(mail_handler: $handler);
            $resource = RedisQueueResource::make($this->redis[$client]->connection(), 'vf:test:'.$client);
            $loop->resource('redis', $resource);

            $this->redis[$client]->connection()->rpush('vf:test:'.$client, 'not json');
            $loop->at(0.3, fn () => $loop->stop());
            $loop->run();

            expect($handler->mail[0])->toBeInstanceOf(RedisMessage::class)
                ->and($handler->mail[0]->raw)->toBe('not json');
        });
    });
}

it('predis: wakes on the message, not on a poll', function () {
    // tick_budget_ms is the poller's pace once no timer is due. The loop pumps
    // before a later timer can, so the handler is what observes the wake — and
    // what stops the run, or the re-armed BLPOP waits forever.
    $received_at = null;
    $loop = null;
    $handler = new class($received_at, $loop) implements Receivable {
        public function __construct(private mixed &$received_at, private mixed &$loop) {}

        public function handOff(MailCollection $mail): void
        {
            if ($mail->mail()->isEmpty() || ! is_null($this->received_at)) {
                return;
            }

            $this->received_at = microtime(true);
            $this->loop->stop();
        }
    };
    $loop = new EventLoop(tick_budget_ms: 500, mail_handler: $handler);
    $resource = RedisQueueResource::make($this->redis['predis']->connection(), 'vf:test:wake');
    $loop->resource('redis', $resource);

    expect($resource)->toBeInstanceOf(StreamWatchable::class);

    $loop->at(0.05, fn () => $this->redis['phpredis']->connection()->rpush('vf:test:wake', json_encode(['class' => TestEvent::class, 'data' => ['name' => 'x', 'payload' => null]])));
    $loop->at(1, fn () => $loop->stop());
    $sent_at = microtime(true) + 0.05;
    $loop->run();

    expect($received_at)->not->toBeNull()
        ->and($received_at - $sent_at)->toBeLessThan(0.1);
});
