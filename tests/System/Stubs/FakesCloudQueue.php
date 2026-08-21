<?php

namespace Tests\System\Stubs;

use Aws\HandlerList;
use Aws\Sqs\SqsClient;
use Mockery\MockInterface;
use Voyager\Http\Client\ConnectionException;
use Voyager\MagicAliases\Http;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Queue\Connectors\ConnectorInterface;
use Voyager\Queue\Failed\FileFailedJobProvider;
use Voyager\Queue\SqsQueue;
use Voyager\System\Cloud\Events;
use Voyager\System\Cloud\Queue;
use Voyager\System\Cloud\QueueConnector;
use Voyager\Testing\Fakes\QueueFake;

/**
 * The Cloud queue doubles the queue tests are built on.
 *
 * Upstream keeps these as private methods on the TestCase; a Pest file has no
 * class to hang them off, and they lean on `$this->app` and `$this->mock()`
 * throughout, so they move to a trait rather than to global functions.
 */
trait FakesCloudQueue
{
    /**
     * @return array{Queue, MockInterface<SqsClient>}
     */
    private function mockedQueue()
    {
        $client = $this->mock(SqsClient::class);
        $client->shouldReceive('getHandlerList')->andReturn(new HandlerList());

        $this->app->instance(QueueConnector::class, new QueueConnector(new class($client) implements ConnectorInterface
        {
            public function __construct(private $client)
            {
                //
            }

            public function connect($config)
            {
                return new SqsQueue(
                    $this->client,
                    $config['queue'],
                    $config['prefix'] ?? '',
                    $config['suffix'] ?? '',
                    $config['after_commit'] ?? null,
                    $config['overflow'] ?? [],
                );
            }
        }, $this->app));

        $this->app['queue']->addConnector('cloud', $this->app->factory(QueueConnector::class));

        return [$this->app['queue']->connection('cloud'), $client];
    }

    private function fakeEvents()
    {
        return $this->app->instance(Events::class, new class('test-socket') extends Events
        {
            public array $emitted = [];
            public string $stream = '';

            protected function connected(): bool
            {
                return true;
            }

            protected function write(string $payload): void
            {
                $this->stream .= $payload;

                foreach (explode("\n", rtrim($payload, "\n")) as $write) {
                    $this->emitted[] = json_decode($write, associative: true);
                }
            }
        });
    }

    /**
     * Build a Cloud queue whose agent runtime socket is faked via Http::fake().
     *
     * The returned agent exposes pushJob() to script the next GET /next
     * responses; once drained the agent answers 204. POST /result requests are
     * recorded by the HTTP fake and can be asserted with Http::assertSent().
     *
     * @return array{Queue, object{jobs: array}}
     */
    private function fakeQueue($sqs = null)
    {
        // Enable the agent so pop() long-polls the faked runtime socket.
        $this->app['config']->set('queue.connections.cloud.agent', [
            'enabled' => true,
            'socket' => '/tmp/cloud-agent.sock',
        ]);

        // A real client suffices while the SQS seams stay no-ops; callers asserting
        // direct SQS calls pass a mock instead.
        $sqs ??= new SqsClient(['region' => 'us-east-2', 'version' => 'latest', 'credentials' => false]);

        $fakeQueue = new class($this->app, $sqs) extends QueueFake
        {
            public function __construct($app, private $sqs)
            {
                parent::__construct($app);
            }

            public function getQueue($queue)
            {
                $queue ??= 'default';

                return config('queue.connections.cloud.connection.prefix').'/'.$queue.config('queue.connections.cloud.connection.suffix');
            }

            public function getContainer()
            {
                return $this->app;
            }

            public function getSqs()
            {
                return $this->sqs;
            }

            public function getConnectionName()
            {
                return 'cloud';
            }

            public function setConfig(array $config)
            {
                return $this;
            }

            public function setContainer($container)
            {
                return $this;
            }
        };

        $this->app->instance(QueueConnector::class, new QueueConnector(new class($fakeQueue) implements ConnectorInterface
        {
            public function __construct(private $fakeQueue)
            {
                //
            }

            public function connect($config)
            {
                return $this->fakeQueue;
            }
        }, $this->app));

        $this->app['queue']->addConnector('cloud', $this->app->factory(QueueConnector::class));

        $agent = $this->fakeAgent();

        $connection = $this->app['queue']->connection('cloud');

        // The agent only serves a queue:work worker popping its queue, so present
        // as one for pop() (see usesAgent()). Set after connecting so the
        // connector's worker wiring - which expects the cloud failed-job provider
        // - isn't exercised here. Restored in tearDown().
        $_SERVER['argv'] = ['artisan', 'queue:work'];

        return [$connection, $agent];
    }

    /**
     * Fake the cloud-agent runtime socket with Http::fake(): GET /next serves
     * scripted jobs (204 once drained) and POST /result is accepted (and
     * recorded for assertions). The returned object scripts jobs via pushJob().
     */
    private function fakeAgent()
    {
        $agent = new class
        {
            public array $jobs = [];

            public int $resultStatus = 200;

            public bool $resultUnreachable = false;

            public $nextResponse = null;

            /**
             * Exceptions GET /next throws, one per request, before serving jobs.
             */
            public array $nextExceptions = [];

            /**
             * The number of GET /next requests the agent has received.
             */
            public int $nextRequests = 0;

            public function pushJob(array $job = []): array
            {
                $job = array_merge([
                    'messageId' => (string) Str::uuid(),
                    'receiptHandle' => 'receipt-handle',
                    // The agent always reports the SQS queue URL the message came from.
                    'queueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/default',
                    'body' => json_encode(['job' => MyJob::class, 'data' => []]),
                    // SQS always returns ApproximateReceiveCount, so mirror it.
                    'attributes' => ['ApproximateReceiveCount' => 1],
                ], $job);

                $this->jobs[] = $job;

                return $job;
            }
        };

        Http::fake(function ($request) use ($agent) {
            if (str_ends_with($request->url(), '/next')) {
                $agent->nextRequests++;

                if ($exception = array_shift($agent->nextExceptions)) {
                    throw $exception;
                }

                if ($agent->nextResponse !== null) {
                    return $agent->nextResponse;
                }

                $job = array_shift($agent->jobs);

                return $job === null
                    ? Http::response('', 204)
                    : Http::response($job, 200);
            }

            if (str_ends_with($request->url(), '/result')) {
                if ($agent->resultUnreachable) {
                    throw new ConnectionException('Connection refused');
                }

                return Http::response('', $agent->resultStatus);
            }

            return Http::response('', 404);
        });

        return $agent;
    }

    /**
     * Build the exception a timed-out GET /next long-poll surfaces as. The
     * message mirrors the curl handler's format, which the client preserves
     * when wrapping Guzzle's ConnectException.
     */
    private function pollTimeoutException(): ConnectionException
    {
        return new ConnectionException(
            'cURL error 28: Operation timed out after 76900 milliseconds with 0 bytes received (see https://curl.se/libcurl/c/libcurl-errors.html) for http://localhost/next'
        );
    }

    private function fakeFailer()
    {
        return new FileFailedJobProvider(tempnam(sys_get_temp_dir(), 'cloud_failed_job_test_'));
    }

    /**
     * Assert the exact sequence of POST /result bodies sent to the agent.
     */
    private function assertAgentResults(array $expected): void
    {
        $results = Http::recorded(fn ($request) => str_ends_with($request->url(), '/result'))
            ->map(fn ($record) => $record[0]->data())
            ->values()
            ->all();

        expect($results)->toBe($expected);
    }
}
