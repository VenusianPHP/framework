<?php

use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Queue\Fixtures\QueueApp;
use Venusian\Tests\Broadcasting\Fixtures\PriceChanged;
use Venusian\Tests\Broadcasting\Fixtures\OrderShipped;
use Voyager\Broadcasting\BroadcastManager;
use Voyager\Broadcasting\Broadcasters\NullBroadcaster;
use Voyager\Contracts\Broadcasting\Factory;

beforeEach(function () {
    $this->app = QueueApp::boot();
    $this->log = LogApp::channel($this->app, 'broadcasts');
    $this->app['config']->set('broadcasting.default', 'log');
    $this->app['config']->set('logging.default', 'broadcasts');
});

afterEach(fn () => QueueApp::tearDown($this->app, $this));

/** The broadcasts the log broadcaster wrote, as [event, channels, payload]. */
function broadcasts(string $log): array
{
    preg_match_all('/Broadcasting \[(.+?)\] on channels \[(.*?)\] with payload:\s*(\{.*?\n\})/s', implode("\n", LogApp::lines($log)), $matches, PREG_SET_ORDER);

    return array_map(fn (array $match) => [$match[1], $match[2], json_decode($match[3], true)], $matches);
}

it('resolves the manager and the default broadcaster', function () {
    expect($this->app->make(Factory::class))->toBeInstanceOf(BroadcastManager::class)
        ->and($this->app['broadcast'])->toBe($this->app->make(Factory::class))
        ->and($this->app['broadcast']->connection('null'))->toBeInstanceOf(NullBroadcaster::class);
});

it('broadcasts a ShouldBroadcast event through the queue, its public properties as the payload', function () {
    broadcast(new OrderShipped(42, 'ups'));

    expect(broadcasts($this->log))->toBe([
        ['order.shipped', 'orders, private-customer.42', ['order' => 42, 'carrier' => 'ups', 'connection' => null]],
    ]);
});

it('broadcasts a dispatched ShouldBroadcast signal, unless broadcastWhen says no', function () {
    $this->app['signals']->dispatch(new OrderShipped(7, 'dhl'));
    $this->app['signals']->dispatch(new OrderShipped(8, 'none'));

    expect(array_column(broadcasts($this->log), 1))->toBe(['orders, private-customer.7']);
});

it('leaves a queued broadcast on its queue until a worker runs it', function () {
    broadcast(new OrderShipped(9, 'fedex', connection: 'database'));
    $before = broadcasts($this->log);

    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--once' => true]);

    expect($before)->toBe([])
        ->and(array_column(broadcasts($this->log), 0))->toBe(['order.shipped']);
});

it('broadcasts a ShouldBroadcastNow event right away, whatever the default queue is', function () {
    $this->app['config']->set('queue.default', 'database');

    broadcast(new PriceChanged('sku-1', 1999));

    expect(broadcasts($this->log))->toBe([[PriceChanged::class, 'prices', ['sku' => 'sku-1', 'price' => 19.99, 'socket' => null]]])
        ->and($this->app['queue']->connection('database')->size())->toBe(0);
});

it('publishes on redis channels under the connection prefix', function () {
    $prefix = $this->app['config']->get('database.redis.options.prefix');
    $this->app['config']->set('broadcasting.default', 'redis');

    $subscriber = stream_socket_client('tcp://127.0.0.1:6379', timeout: 1);
    fwrite($subscriber, "SUBSCRIBE {$prefix}prices\r\n");
    fread($subscriber, 1024);

    broadcast(new PriceChanged('sku-2', 500));

    stream_set_timeout($subscriber, 2);
    $message = '';
    while (! str_contains($message, '"event"') && ! feof($subscriber)) {
        $message .= fread($subscriber, 4096);
    }
    fclose($subscriber);

    preg_match('/(\{.*\})/', $message, $json);

    expect(json_decode($json[1], true))->toBe(['event' => PriceChanged::class, 'data' => ['sku' => 'sku-2', 'price' => 5, 'socket' => null], 'socket' => null]);
})->skip(fn () => ! QueueApp::redisReachable(), 'needs the local Redis');

it('asks for the pusher package by name when a pusher connection is used without it', function () {
    expect(fn () => $this->app['broadcast']->connection('pusher'))
        ->toThrow(RuntimeException::class, 'pusher/pusher-php-server');
})->skip(fn () => class_exists(\Pusher\Pusher::class), 'pusher is installed here');
