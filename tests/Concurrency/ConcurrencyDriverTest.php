<?php

namespace Tests\Concurrency;

use Exception;
use PHPUnit\Framework\TestCase;
use Voyager\Concurrency\ProcessDriver;
use Voyager\Concurrency\SyncDriver;
use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\Process\Factory as ProcessFactory;
use Voyager\Vessel\Vessel;

class ConcurrencyDriverTest extends TestCase
{
    protected $previousVessel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousVessel = Vessel::getInstance();

        $vessel = new class extends Vessel
        {
            public function basePath($path = '')
            {
                return __DIR__.($path != '' ? DIRECTORY_SEPARATOR.$path : $path);
            }
        };

        $vessel->singleton(DeferredCallbackCollection::class);

        Vessel::setInstance($vessel);
    }

    protected function tearDown(): void
    {
        Vessel::setInstance($this->previousVessel);

        parent::tearDown();
    }

    public function testTheSyncDriverRunsTasksAndPreservesKeys()
    {
        $results = new SyncDriver()->run([
            'first' => fn () => 1 + 1,
            'second' => fn () => 2 + 2,
        ]);

        $this->assertSame(['first' => 2, 'second' => 4], $results);
    }

    public function testTheSyncDriverPreservesCallbackOrder()
    {
        $results = new SyncDriver()->run([
            fn () => 'first',
            fn () => 'second',
            fn () => 'third',
        ]);

        $this->assertSame(['first', 'second', 'third'], $results);
    }

    public function testTheSyncDriverWrapsASingleClosure()
    {
        $this->assertSame([2], new SyncDriver()->run(fn () => 1 + 1));
    }

    public function testTheSyncDriverLetsExceptionsSurface()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Task failed.');

        new SyncDriver()->run([fn () => throw new Exception('Task failed.')]);
    }

    public function testTheSyncDriverDefersTasksUntilTheCallbackIsInvoked()
    {
        $ran = false;

        $deferred = new SyncDriver()->defer([function () use (&$ran) {
            $ran = true;
        }]);

        $this->assertInstanceOf(DeferredCallback::class, $deferred);
        $this->assertFalse($ran);

        $deferred();

        $this->assertTrue($ran);
    }

    public function testTheProcessDriverMapsChildResultsBackOntoTheirKeys()
    {
        $driver = new ProcessDriver($this->factoryReturning([
            $this->successful(2),
            $this->successful(4),
        ]));

        $this->assertSame(['first' => 2, 'second' => 4], $driver->run([
            'first' => fn () => 1 + 1,
            'second' => fn () => 2 + 2,
        ]));
    }

    public function testTheProcessDriverStripsTrailingGzipOutputFromChildren()
    {
        $driver = new ProcessDriver($this->factoryReturning([
            $this->successful('venusian')."\x1f\x8b".'binary noise',
        ]));

        $this->assertSame(['venusian'], $driver->run([fn () => 'venusian']));
    }

    public function testTheProcessDriverThrowsWhenAChildProcessFails()
    {
        $factory = new ProcessFactory;
        $factory->fake(['*' => $factory->result(errorOutput: 'Segmentation fault', exitCode: 1)]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Concurrent process failed with exit code [1]. Message: Segmentation fault');

        new ProcessDriver($factory)->run([fn () => 1 + 1]);
    }

    public function testTheProcessDriverRethrowsAChildExceptionFromItsMessage()
    {
        $driver = new ProcessDriver($this->factoryReturning([
            json_encode([
                'successful' => false,
                'exception' => Exception::class,
                'message' => 'This is a different exception',
                'parameters' => [],
            ]),
        ]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('This is a different exception');

        $driver->run([fn () => 1 + 1]);
    }

    public function testTheProcessDriverRethrowsAChildExceptionWithItsConstructorParameters()
    {
        $driver = new ProcessDriver($this->factoryReturning([
            json_encode([
                'successful' => false,
                'exception' => ExceptionWithParam::class,
                'message' => 'ignored in favour of the parameters',
                'parameters' => [
                    'uri' => 'https://api.example.com',
                    'statusCode' => 400,
                    'reason' => 'Bad Request',
                    'responseBody' => 'Invalid payload',
                ],
            ]),
        ]));

        $this->expectException(ExceptionWithParam::class);
        $this->expectExceptionMessage('API request to https://api.example.com failed with status 400 Bad Request');

        $driver->run([fn () => 1 + 1]);
    }

    public function testTheProcessDriverDefersTasksUntilTheCallbackIsInvoked()
    {
        $factory = new ProcessFactory;
        $factory->fake();

        $deferred = new ProcessDriver($factory)->defer([fn () => 1 + 1]);

        $this->assertInstanceOf(DeferredCallback::class, $deferred);
        $factory->assertNothingRan();

        $deferred();

        $factory->assertRanTimes(fn () => true, 1);
    }

    /**
     * Build a process factory whose pooled children answer with the given output in order.
     */
    protected function factoryReturning(array $outputs): ProcessFactory
    {
        $factory = new ProcessFactory;

        $factory->fake(['*' => $factory->sequence(
            array_map(fn ($output) => $factory->result(output: $output), $outputs)
        )]);

        return $factory;
    }

    /**
     * Build the JSON payload a successful child process writes to stdout.
     */
    protected function successful(mixed $result): string
    {
        return json_encode(['successful' => true, 'result' => serialize($result)]);
    }
}

class ExceptionWithParam extends Exception
{
    public function __construct(
        public string $uri,
        public int $statusCode,
        public string $reason,
        public string|array $responseBody = '',
    ) {
        parent::__construct("API request to {$uri} failed with status $statusCode $reason");
    }
}
