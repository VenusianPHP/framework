<?php

namespace Tests\Concurrency;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Voyager\Concurrency\ConcurrencyManager;
use Voyager\Concurrency\ProcessDriver;
use Voyager\Concurrency\SyncDriver;
use Voyager\Config\Repository;
use Voyager\Process\Factory as ProcessFactory;
use Voyager\Vessel\Vessel;

class ConcurrencyManagerTest extends TestCase
{
    protected $previousVessel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousVessel = Vessel::getInstance();
    }

    protected function tearDown(): void
    {
        Vessel::setInstance($this->previousVessel);

        parent::tearDown();
    }

    public function testItResolvesTheSyncDriver()
    {
        $manager = new ConcurrencyManager($this->app());

        $this->assertInstanceOf(SyncDriver::class, $manager->driver('sync'));
    }

    public function testItResolvesTheProcessDriver()
    {
        $manager = new ConcurrencyManager($this->app());

        $this->assertInstanceOf(ProcessDriver::class, $manager->driver('process'));
    }

    public function testItDefaultsToTheProcessDriver()
    {
        $manager = new ConcurrencyManager($this->app());

        $this->assertSame('process', $manager->getDefaultInstance());
        $this->assertInstanceOf(ProcessDriver::class, $manager->driver());
    }

    public function testTheDefaultInstanceIsReadFromConfiguration()
    {
        $manager = new ConcurrencyManager($this->app(['concurrency.default' => 'sync']));

        $this->assertSame('sync', $manager->getDefaultInstance());
        $this->assertInstanceOf(SyncDriver::class, $manager->driver());
    }

    public function testTheLegacyDriverConfigurationKeyIsHonored()
    {
        $manager = new ConcurrencyManager($this->app(['concurrency.driver' => 'sync']));

        $this->assertSame('sync', $manager->getDefaultInstance());
    }

    public function testTheDefaultInstanceCanBeSet()
    {
        $manager = new ConcurrencyManager($app = $this->app());

        $manager->setDefaultInstance('sync');

        $this->assertSame('sync', $manager->getDefaultInstance());
        $this->assertSame('sync', $app['config']['concurrency.default']);
        $this->assertSame('sync', $app['config']['concurrency.driver']);
    }

    public function testInstancesAreResolvedOnce()
    {
        $manager = new ConcurrencyManager($this->app());

        $this->assertSame($manager->driver('sync'), $manager->driver('sync'));
    }

    public function testUnknownDriversAreRejected()
    {
        $manager = new ConcurrencyManager($this->app());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Instance driver [swoole] is not supported.');

        $manager->driver('swoole');
    }

    public function testTheForkDriverMayNotBeUsedOutsideTheConsole()
    {
        $manager = new ConcurrencyManager($this->app(runningInConsole: false));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Due to PHP limitations, the fork driver may not be used within web requests.');

        $manager->driver('fork');
    }

    public function testTheForkDriverRequiresTheSpatieForkPackage()
    {
        if (class_exists(\Spatie\Fork\Fork::class)) {
            $this->markTestSkipped('The spatie/fork package is installed.');
        }

        $manager = new ConcurrencyManager($this->app());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please install the "spatie/fork" Composer package in order to utilize the "fork" driver.');

        $manager->driver('fork');
    }

    /**
     * Build the smallest container the manager needs.
     *
     * The manager only ever asks the application for its configuration, for a
     * process factory, and whether it is running in the console, so a plain
     * Vessel carrying those three answers stands in for a booted application.
     */
    protected function app(array $config = [], bool $runningInConsole = true)
    {
        $vessel = new class($runningInConsole) extends Vessel
        {
            public function __construct(protected bool $console)
            {
                //
            }

            public function runningInConsole()
            {
                return $this->console;
            }
        };

        $vessel->instance('config', new Repository($config));
        $vessel->instance(ProcessFactory::class, new ProcessFactory);

        Vessel::setInstance($vessel);

        return $vessel;
    }
}
