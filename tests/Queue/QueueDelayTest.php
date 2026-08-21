<?php

namespace Tests\Queue;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\NutsAndBolts\MagicAliases\Queue;
use PHPUnit\Framework\TestCase;
use Voyager\Bus\Dispatcher as BusDispatcher;
use Voyager\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Voyager\Contracts\Queue\Factory as QueueFactory;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Queue\QueueManager;
use Voyager\Testing\Fakes\QueueFake;
use Voyager\Vessel\Vessel;

class QueueDelayTest extends TestCase
{
    /**
     * Laravel runs these against a Testbench application. `Queue::fake()` and
     * the `dispatch()` helper only need a container carrying a queue manager
     * and a bus dispatcher, so a plain Vessel is wired with those two.
     */
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Vessel;
        Vessel::setInstance($this->app);

        $this->app->singleton('queue', fn ($app) => new QueueManager($app));
        $this->app->singleton(QueueFactory::class, fn ($app) => $app['queue']);
        $this->app->singleton(BusDispatcher::class, fn ($app) => new BusDispatcher(
            $app, fn ($connection = null) => $app['queue']->connection($connection)
        ));
        $this->app->alias(BusDispatcher::class, BusDispatcherContract::class);

        MagicAlias::setMagicAliasApplication($this->app);
    }

    protected function tearDown(): void
    {
        MagicAlias::clearResolvedInstances();
        MagicAlias::setMagicAliasApplication(null);
        Vessel::setInstance(null);

        parent::tearDown();
    }

    public function test_queue_delay()
    {
        Queue::fake();

        $job = new TestJob;

        dispatch($job);

        $this->assertEquals(60, $job->delay);
    }

    public function test_queue_without_delay()
    {
        Queue::fake();

        $job = new TestJob;

        dispatch($job->withoutDelay());

        $this->assertEquals(0, $job->delay);
    }

    public function test_pending_dispatch_without_delay()
    {
        Queue::fake();

        $job = new TestJob;

        dispatch($job)->withoutDelay();

        $this->assertEquals(0, $job->delay);
    }
}

class TestJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->delay(60);
    }
}
