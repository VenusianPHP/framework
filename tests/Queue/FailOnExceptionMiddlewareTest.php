<?php

namespace Tests\Queue;

use Voyager\Bus\Dispatcher;
use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Bus\Dispatchable;
use Voyager\Queue\CallQueuedHandler;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Jobs\FakeJob;
use Voyager\Queue\Middleware\FailOnException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Voyager\Vessel\Vessel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Throwable;

class FailOnExceptionMiddlewareTest extends TestCase
{
    /**
     * Laravel runs this against a Testbench application. The middleware only
     * ever needs a container to resolve the job through, so a plain Vessel
     * stands in for one.
     */
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Vessel;
        Vessel::setInstance($this->app);

        FailOnExceptionMiddlewareTestJob::$_middleware = [];
    }

    protected function tearDown(): void
    {
        Vessel::setInstance(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{class-string<\Throwable>, FailOnException, bool}>
     */
    public static function middlewareDataProvider(): array
    {
        return [
            'exception is in list' => [
                InvalidArgumentException::class,
                new FailOnException([InvalidArgumentException::class]),
                true,
            ],
            'exception is not in list' => [
                LogicException::class,
                new FailOnException([InvalidArgumentException::class]),
                false,
            ],
        ];
    }

    #[DataProvider('middlewareDataProvider')]
    public function test_middleware(
        string $thrown,
        FailOnException $middleware,
        bool $expectedToFail
    ): void {
        FailOnExceptionMiddlewareTestJob::$_middleware = [$middleware];
        $job = new FailOnExceptionMiddlewareTestJob($thrown);
        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $fakeJob = new FakeJob();
        $job->setJob($fakeJob);

        try {
            $instance->call($fakeJob, [
                'command' => serialize($job),
            ]);
            $this->fail('Did not throw exception');
        } catch (Throwable $e) {
            $this->assertInstanceOf($thrown, $e);
        }

        $expectedToFail ? $job->assertFailed() : $job->assertNotFailed();
    }

    #[TestWith(['abc', true])]
    #[TestWith(['tots', false])]
    public function test_can_test_against_job_properties($value, bool $expectedToFail): void
    {
        FailOnExceptionMiddlewareTestJob::$_middleware = [
            new FailOnException(fn ($thrown, $job) => $job->value === 'abc'),
        ];

        $job = new FailOnExceptionMiddlewareTestJob(InvalidArgumentException::class, $value);
        $instance = new CallQueuedHandler(new Dispatcher($this->app), $this->app);

        $fakeJob = new FakeJob();
        $job->setJob($fakeJob);

        try {
            $instance->call($fakeJob, [
                'command' => serialize($job),
            ]);
            $this->fail('Did not throw exception');
        } catch (Throwable) {
            //
        }

        $expectedToFail ? $job->assertFailed() : $job->assertNotFailed();
    }
}

class FailOnExceptionMiddlewareTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public static array $_middleware = [];

    public int $tries = 2;

    public function __construct(private $throws, public $value = null)
    {
    }

    public function handle()
    {
        throw new $this->throws;
    }

    public function middleware(): array
    {
        return self::$_middleware;
    }
}
