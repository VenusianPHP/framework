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

test('queue size', function () {
    Queue::fake();

    expect(Queue::size())->toBe(0)
        ->and(Queue::size('Q2'))->toBe(0);

    $job = new TestJob1;

    dispatch($job);
    dispatch(new TestJob2);
    dispatch($job)->onQueue('Q2');

    expect(Queue::size())->toBe(2)
        ->and(Queue::size('Q2'))->toBe(1);
});

class TestJob1 implements ShouldQueue
{
    use Queueable;
}

class TestJob2 implements ShouldQueue
{
    use Queueable;
}
