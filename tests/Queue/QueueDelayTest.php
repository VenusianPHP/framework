<?php

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\NutsAndBolts\MagicAliases\Queue;
use Voyager\Bus\Dispatcher as BusDispatcher;
use Voyager\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Voyager\Contracts\Queue\Factory as QueueFactory;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Queue\QueueManager;
use Voyager\Testing\Fakes\QueueFake;
use Voyager\Vessel\Vessel;

/**
 * Laravel runs these against a Testbench application. `Queue::fake()` and
 * the `dispatch()` helper only need a container carrying a queue manager
 * and a bus dispatcher, so a plain Vessel is wired with those two.
 */
beforeEach(function () {
    $this->app = new Vessel;
    Vessel::setInstance($this->app);

    $this->app->singleton('queue', fn ($app) => new QueueManager($app));
    $this->app->singleton(QueueFactory::class, fn ($app) => $app['queue']);
    $this->app->singleton(BusDispatcher::class, fn ($app) => new BusDispatcher(
        $app, fn ($connection = null) => $app['queue']->connection($connection)
    ));
    $this->app->alias(BusDispatcher::class, BusDispatcherContract::class);

    MagicAlias::setMagicAliasApplication($this->app);
});

afterEach(function () {
    MagicAlias::clearResolvedInstances();
    MagicAlias::setMagicAliasApplication(null);
    Vessel::setInstance(null);
});

test('queue delay', function () {
    Queue::fake();

    $job = new TestJob;

    dispatch($job);

    expect($job->delay)->toBe(60);
});

test('queue without delay', function () {
    Queue::fake();

    $job = new TestJob;

    dispatch($job->withoutDelay());

    expect($job->delay)->toBe(0);
});

test('pending dispatch without delay', function () {
    Queue::fake();

    $job = new TestJob;

    dispatch($job)->withoutDelay();

    expect($job->delay)->toBe(0);
});

class TestJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->delay(60);
    }
}
