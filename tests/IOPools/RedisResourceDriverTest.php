<?php

use Voyager\Contracts\IOPools\Completion as CompletionContract;
use Voyager\Contracts\IOPools\Sendable;
use Voyager\IOPools\DTO\RedisMessage;
use Voyager\IOPools\Drivers\RedisResourceDriver;
use Voyager\IOPools\IOPoolDock;
use Voyager\Redis\Connections\Connection;
use Voyager\Vessel\Vessel as Container;

/**
 * A Connection whose lists live in an array: rpush/lpop only, no server.
 */
class FakeRedisConnection extends Connection
{
    /** @var array<string, list<string>> */
    public array $lists = [];

    public function createSubscription($channels, Closure $callback, $method = 'subscribe')
    {
        throw new BadMethodCallException('Not a real connection.');
    }

    public function command($method, array $parameters = [])
    {
        $key = $parameters[0];

        if ($method === 'rpush') {
            return $this->lists[$key][] = $parameters[1];
        }

        if ($method === 'lpop') {
            if (! isset($this->lists[$key]) || $this->lists[$key] === []) {
                return null;
            }

            return array_shift($this->lists[$key]);
        }

        throw new BadMethodCallException("Fake redis cannot {$method}.");
    }
}

class TraveledCompletion implements CompletionContract, Sendable
{
    public function __construct(
        public readonly string $name,
        public readonly string $verdict,
    ) {}

    public function ok(): bool
    {
        return true;
    }

    public function toSendable(): array
    {
        return ['name' => $this->name, 'verdict' => $this->verdict];
    }

    public static function fromSendable(array $data): static
    {
        return new static((string) $data['name'], (string) $data['verdict']);
    }
}

beforeEach(function () {
    $this->redis = new FakeRedisConnection;
    $this->dock = new IOPoolDock(new Container, ['resources' => []]);
    $this->driver = (new RedisResourceDriver([], $this->redis, $this->dock))->key('sketch:mail');
});

test('posted mail round-trips the list and arrives as its own class', function () {
    $this->driver->post(new TraveledCompletion('workflow.render', 'done'));
    $this->driver->tick();

    $mail = $this->dock->drain()->sole();

    expect($mail)->toBeInstanceOf(TraveledCompletion::class)
        ->and($mail->name)->toBe('workflow.render')
        ->and($mail->verdict)->toBe('done');
});

test('the sweep takes everything buffered, in posting order, and stops when the list is empty', function () {
    $this->driver->post(new TraveledCompletion('a', '1'));
    $this->driver->post(new TraveledCompletion('b', '2'));
    $this->driver->tick();

    expect($this->dock->drain()->map(fn ($m) => $m->name)->all())->toBe(['a', 'b']);

    $this->driver->tick();
    expect($this->dock->drain()->isEmpty())->toBeTrue();
});

test('the batch cap bounds one sweep; the remainder waits for the next tick', function () {
    $driver = (new RedisResourceDriver([], $this->redis, $this->dock, batch: 2))->key('sketch:mail');

    foreach (['a', 'b', 'c'] as $name) {
        $driver->post(new TraveledCompletion($name, 'x'));
    }

    $driver->tick();
    expect($this->dock->drain()->count())->toBe(2);

    $driver->tick();
    expect($this->dock->drain()->sole()->name)->toBe('c');
});

test('post can aim at another key, and key() retargets the sweep', function () {
    $this->driver->post(new TraveledCompletion('elsewhere', 'x'), 'other:mail');
    $this->driver->tick();
    expect($this->dock->drain()->isEmpty())->toBeTrue();

    $this->driver->key('other:mail')->tick();
    expect($this->dock->drain()->sole()->name)->toBe('elsewhere');
});

test('junk on the list arrives as RedisMessage — the driver never eats mail', function () {
    $this->redis->command('rpush', ['sketch:mail', 'not even json {']);
    $this->redis->command('rpush', ['sketch:mail', json_encode(['class' => 'Nope\\Missing', 'data' => []])]);
    $this->redis->command('rpush', ['sketch:mail', json_encode(['class' => FakeRedisConnection::class, 'data' => []])]);

    $this->driver->tick();
    $mail = $this->dock->drain();

    expect($mail->count())->toBe(3)
        ->and($mail->every(fn ($m) => $m instanceof RedisMessage))->toBeTrue()
        ->and($mail->first()->raw)->toBe('not even json {')
        ->and($mail->first()->name)->toBe('sketch:mail');
});
