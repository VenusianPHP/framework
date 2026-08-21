<?php

use Voyager\Bus\Dispatcher;
use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Bus\Dispatchable;
use Voyager\Queue\CallQueuedHandler;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Jobs\FakeJob;
use Voyager\Queue\Middleware\FailOnException;
use Voyager\Vessel\Vessel;

beforeEach(function () {
    // Laravel runs this against a Testbench application. The middleware only
    // ever needs a container to resolve the job through, so a plain Vessel
    // stands in for one.
    $this->app = new Vessel;
    Vessel::setInstance($this->app);

    FailOnExceptionMiddlewareTestJob::$_middleware = [];
});

afterEach(function () {
    Vessel::setInstance(null);
});

test('middleware', function (string $thrown, FailOnException $middleware, bool $expectedToFail) {
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
})->with([
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
]);

test('can test against job properties', function ($value, bool $expectedToFail) {
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
})->with([
    ['abc', true],
    ['tots', false],
]);

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
