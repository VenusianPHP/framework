<?php

namespace Tests\System\Stubs;

use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Queue\Worker;

/**
 * The base case for the Cloud queue tests.
 *
 * Upstream puts the #[WithMigration] attributes, defineEnvironment() and the
 * setUp()/tearDown() pair on the test class itself. A Pest file has no class to
 * carry the attributes or the hook, and its beforeEach()/afterEach() run inside
 * setUp()/tearDown() — i.e. after the application has booted and before it is
 * torn down. The queue tests depend on the other order: the LARAVEL_CLOUD
 * environment has to exist while the application boots, and the spoofed argv
 * has to be restored before the application is torn down. So both hooks live
 * here, and the Pest file keeps only the part that runs after the boot.
 */
#[WithMigration]
#[WithMigration('laravel', 'queue')]
abstract class CloudQueueCase extends TestCase
{
    /**
     * The original $_SERVER['argv'], restored in tearDown() after fakeQueue()
     * spoofs a queue:work worker.
     *
     * @var array|null
     */
    private $savedArgv = null;

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', Str::random(32));
    }

    protected function setUp(): void
    {
        $this->savedArgv = $_SERVER['argv'];

        Worker::$restartable = true;
        Worker::$pausable = true;
        $_SERVER['LARAVEL_CLOUD'] = '1';
        $_SERVER['LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG'] = json_encode([
            'driver' => 'cloud',
            'connection' => [
                'driver' => 'sqs',
                'region' => 'us-east-2',
                'prefix' => 'https://sqs.us-east-2.amazonaws.com/1234567',
                'suffix' => '-env-8280cf2c-2081-47e8-a1f1-9cdfcba8618f',
                'queue' => 'default',
            ],
        ]);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore before the parent tears down (and even when setUp failed) so
        // a spoofed queue:work argv never leaks into the rest of the process.
        if ($this->savedArgv !== null) {
            $_SERVER['argv'] = $this->savedArgv;
            $this->savedArgv = null;
        }

        parent::tearDown();

        unset($_SERVER['LARAVEL_CLOUD'], $_SERVER['LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG']);
        Worker::$restartable = true;
        Worker::$pausable = true;
    }
}
