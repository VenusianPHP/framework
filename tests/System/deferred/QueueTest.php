<?php

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Ramsey\Uuid\Uuid;
use Tests\System\Stubs\CloudQueueCase;
use Tests\System\Stubs\FakesCloudQueue;
use Voyager\Contracts\Encryption\DecryptException;
use Voyager\Database\LostConnectionDetector;
use Voyager\Http\Client\ConnectionException;
use Voyager\Http\Client\RequestException;
use Voyager\MagicAliases\Crypt;
use Voyager\MagicAliases\DB;
use Voyager\MagicAliases\Http;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Sleep;
use Voyager\Queue\Connectors\SqsConnector;
use Voyager\Queue\Events\WorkerStopping;
use Voyager\Queue\Jobs\FakeJob;
use Voyager\Queue\Jobs\SqsJob;
use Voyager\Queue\Worker;
use Voyager\Queue\WorkerStopReason;
use Voyager\System\Cloud;
use Voyager\System\Cloud\AgentAwareLostConnectionDetector;
use Voyager\System\Cloud\AgentUnreachableException;
use Voyager\System\Cloud\CloudJob;
use Voyager\System\Cloud\Events;
use Voyager\System\Cloud\FailedJobProvider;
use Voyager\System\Cloud\ManagedQueueNotFoundException;
use Voyager\System\Cloud\Queue;
use Voyager\System\Cloud\QueueConnector;
use Voyager\System\Testing\DatabaseMigrations;

uses(CloudQueueCase::class, DatabaseMigrations::class, FakesCloudQueue::class);

beforeEach(function () {
    $this->app['config']->set('queue.connections.cloud', json_decode($_SERVER['LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG'], true));
});

test('it disables queue restart polling for managed queues', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        Cloud::bootManagedQueues($this->app);
        expect(Worker::$restartable)->toBeTrue();

        $this->app['queue']->connection('cloud');
        expect(Worker::$restartable)->toBeFalse();
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it disables queue pause polling for managed queues', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        Cloud::bootManagedQueues($this->app);
        expect(Worker::$pausable)->toBeTrue();

        $this->app['queue']->connection('cloud');
        expect(Worker::$pausable)->toBeFalse();
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it configures cloud connection from managed queues config', function () {
    $this->app['config']->set('queue.connections.cloud', null);

    Cloud::configureManagedQueues($this->app);

    $expected = json_decode($_SERVER['LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG'], true);
    $expected['connection']['after_commit'] = false;
    $expected['connection']['overflow'] = [
        'enabled' => false,
        'store' => null,
        'always' => false,
        'delete_after_processing' => true,
    ];

    expect($this->app['config']->get('queue.connections.cloud'))->toBe($expected);
});

test('it does not configure managed queues when not enabled', function () {
    unset($_SERVER['LARAVEL_CLOUD_MANAGED_QUEUES_CONFIG']);
    $this->app['config']->set('queue.connections.cloud', null);

    Cloud::configureManagedQueues($this->app);

    expect($this->app['config']->get('queue.connections.cloud'))->toBeNull();
});

test('it binds queue connector and news up sqs connector', function () {
    $this->app->bind(SqsConnector::class, fn () => throw new RuntimeException('Should not be resolved'));
    Cloud::bootManagedQueues($this->app);

    $this->app[QueueConnector::class];
});

test('it binds cloud queue', function () {
    Cloud::bootManagedQueues($this->app);

    expect($this->app['queue']->connection('cloud'))->toBeInstanceOf(Queue::class);
});

test('it binds cloud events as singleton', function () {
    Cloud::bootManagedQueues($this->app);

    expect($this->app->resolved(Events::class))->toBeFalse();
    expect($this->app[Events::class])->toBe($this->app[Events::class]);
});

test('it binds the queue failer', function () {
    Cloud::bootManagedQueues($this->app);

    expect($this->app['queue.failer'])->toBeInstanceOf(FailedJobProvider::class);
});

test('it does not register cloud connector when cloud queue connection is not configured', function () {
    $this->app['config']->set('queue.connections.cloud', null);

    Cloud::bootManagedQueues($this->app);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The [cloud] queue connection has not been configured.');
    $this->app['queue']->connection('cloud');
});

test('it does not register cloud connector when cloud queue connection driver is not cloud', function () {
    $this->app['config']->set('queue.connections.cloud.driver', 'sqs');
    $originalFailer = $this->app['queue.failer'];

    Cloud::bootManagedQueues($this->app);

    expect($this->app->bound(Events::class))->toBeFalse();
    expect($this->app['queue.failer'])->toBe($originalFailer);
});

test('it does not emit events while popping when no jobs are processing and no jobs are available to pop', function () {
    $eventsFake = $this->fakeEvents();
    [$queue] = $this->fakeQueue();

    $queue->pop();

    expect($eventsFake->emitted)->toBe([]);
});

test('agent requests ignore global client configuration', function () {
    $options = null;
    $hasGlobalHeader = null;

    $this->fakeEvents();
    Http::globalOptions([
        'force_ip_resolve' => 'v4',
        'connect_timeout' => 5,
        'timeout' => 30,
    ]);
    Http::globalRequestMiddleware(fn ($request) => $request->withHeader('X-Global', 'Foo'));
    Http::fake(function ($request, $requestOptions) use (&$options, &$hasGlobalHeader) {
        if (str_ends_with($request->url(), '/next')) {
            $options = $requestOptions;
            $hasGlobalHeader = $request->hasHeader('X-Global');
        }
    });

    [$queue] = $this->fakeQueue();

    $queue->pop();

    expect($options)->toBeArray();
    expect($options)->not->toHaveKey('force_ip_resolve');
    expect($options['connect_timeout'])->toBe(10);
    expect($hasGlobalHeader)->toBeFalse();
});

test('it emits started event when job is successfully popped', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $queue->pop();

    expect($eventsFake->emitted)->toBe([[
        '_cloud_event' => 'queue',
        'timestamp' => '2000-01-02 03:04:05.060708',
        'type' => 'started',
        'queue' => 'default',
    ]]);
});

test('it emits processed event when next job is about to pop', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $queue->pop();
    $this->travel(1)->second();
    $queue->pop();

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'started',
            'queue' => 'default',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:06.060708',
            'type' => 'processed',
            'queue' => 'default',
            'duration_ms' => 1000,
        ],
    ]);
});

test('it does not emit events for the same job after it has been processed', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $queue->pop();
    $queue->pop();
    $queue->pop();
    $queue->pop();

    expect($eventsFake->emitted)->toHaveCount(2);
});

test('it remembers the queue for the processed event', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $agent->pushJob();
    // Each pop finishes the previous job, so its processed event must carry
    // the queue that job was popped from, not the queue being popped now.
    // The agent only serves the worker's own queue, so retarget the worker
    // alongside each pop.
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=first'];
    $queue->pop('first');
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=second'];
    $queue->pop('second');
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=third'];
    $queue->pop('third');

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'started',
            'queue' => 'first',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'processed',
            'queue' => 'first',
            'duration_ms' => 0,
        ], [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'started',
            'queue' => 'second',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'processed',
            'queue' => 'second',
            'duration_ms' => 0,
        ],
    ]);
});

test('pop receives from the agent for a non default worker queue', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob(['messageId' => 'message-id']);

    // A managed worker started for a non-default queue is still served by the
    // agent: the agent polls whatever queue the worker is processing.
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=emails'];

    $job = $queue->pop('emails');

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
});

test('pop reads the worker queue from the space separated queue option', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob(['messageId' => 'message-id']);

    // The --queue option may be given space-separated rather than with "=".
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue', 'emails'];

    $job = $queue->pop('emails');

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
});

test('pop receives from sqs when the queue is not the worker queue', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob();

    // A pop for a queue other than the one the worker is processing has no
    // agent feeding it, so it comes from SQS directly and the socket is never
    // polled.
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=emails'];

    expect($queue->pop('not-the-worker-queue'))->toBeNull();

    Http::assertNothingSent();
});

test('pop receives from sqs for a multi queue worker', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob();

    // The agent serves a single queue, so a worker spanning several queues
    // (the worker pops each individually) cannot be served by it and falls
    // back to SQS rather than being handed the agent's one queue for all.
    $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=high,low'];

    expect($queue->pop('high'))->toBeNull();
    expect($queue->pop('low'))->toBeNull();

    Http::assertNothingSent();
});

test('pop receives from the agent when the queue option is omitted', function () {
    $this->fakeEvents();
    // WorkCommand falls back to the connection's top-level "queue" key when
    // --queue is omitted, so workerQueue() must mirror that exact fallback.
    config(['queue.connections.cloud.queue' => 'emails']);
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob(['messageId' => 'message-id']);

    $_SERVER['argv'] = ['artisan', 'queue:work'];

    $job = $queue->pop('emails');

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
});

test('pop receives from sqs when not running as a queue worker', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob();

    // Outside a queue:work worker (e.g. a web request) the agent sidecar is
    // not serving us, so jobs come from SQS directly.
    $_SERVER['argv'] = ['artisan', 'tinker'];

    expect($queue->pop())->toBeNull();

    Http::assertNothingSent();
});

test('it emits failed job events', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $line = __LINE__ + 1;
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here', 'displayName' => 'App\\Jobs\\ProcessPodcast']), new RuntimeException('Whoops!'));
    Str::createUuidsNormally();
    $queue->pop();

    unset($eventsFake->emitted[1]['exception']);
    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'started',
            'queue' => 'default',
        ],
        [
            '_cloud_event' => 'failed_job',
            'id' => '00dc709e-90c4-70c2-87c8-9b7127d20e8f',
            'queue' => 'default',
            'started_at' => '2000-01-02 03:04:05.060708',
            'attempts' => 1,
            'payload' => json_encode(['payload' => 'here', 'displayName' => 'App\\Jobs\\ProcessPodcast']),
            'exception_preview' => 'RuntimeException: Whoops! in '.__FILE__.':'.$line,
            'job_name' => 'App\\Jobs\\ProcessPodcast',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'failed',
            'queue' => 'default',
            'duration_ms' => 0,
        ],
    ]);
});

test('it emits failed job events with exception preview with message', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $line = __LINE__ + 1;
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException('Whoops!'));
    Str::createUuidsNormally();
    $queue->pop();

    expect($eventsFake->emitted[1]['exception_preview'])->toBe('RuntimeException: Whoops! in '.__FILE__.':'.$line);
});

test('it emits failed job events with exception preview without message', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $line = __LINE__ + 1;
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException);
    Str::createUuidsNormally();
    $queue->pop();

    expect($eventsFake->emitted[1]['exception_preview'])->toBe('RuntimeException in '.__FILE__.':'.$line);
});

test('it truncates long exception previews', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException(str_repeat('a', 2000)));
    $queue->pop();

    expect(mb_strlen($eventsFake->emitted[1]['exception_preview']))->toBe(1001);
    expect($eventsFake->emitted[1]['exception_preview'])->toBe('RuntimeException: '.str_repeat('a', 1001 - strlen('RuntimeException: ')));
});

test('it truncates exception previews by width not byte count for multibyte messages', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $message = str_repeat('😎', 4).str_repeat('a', 2000);

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException($message));
    $queue->pop();

    expect(mb_strlen($eventsFake->emitted[1]['exception_preview']))->toBe(1001);
    expect($eventsFake->emitted[1]['exception_preview'])->toBe('RuntimeException: '.str_repeat('😎', 4).str_repeat('a', 1001 - 4 - strlen('RuntimeException: ')));
});

test('it sanitizes invalid utf8 in the exception field', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException("Bad byte: \xFF"));
    Str::createUuidsNormally();
    $queue->pop();

    expect(mb_check_encoding($eventsFake->stream, 'UTF-8'))->toBeTrue();
    expect(mb_check_encoding($eventsFake->emitted[1]['exception'], 'UTF-8'))->toBeTrue();
    expect($eventsFake->emitted[1]['exception'])->toContain('Bad byte: �');
    if (version_compare(PHP_VERSION, '8.3', '<')) {
        expect($eventsFake->emitted[1]['exception_preview'])->toContain('Bad byte: �');
    } else {
        expect($eventsFake->emitted[1]['exception_preview'])->toContain('Bad byte: ?');
    }
});

test('it emits failed job events with job display name', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $failedJobProvider->log('cloud', 'default', json_encode(['displayName' => 'App\\Jobs\\ProcessPodcast']), new RuntimeException('Whoops!'));
    Str::createUuidsNormally();
    $queue->pop();

    expect($eventsFake->emitted[1]['job_name'])->toBe('App\\Jobs\\ProcessPodcast');
});

test('it emits failed job events without job display name', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $failerFake = $this->fakeFailer();
    $failedJobProvider = new FailedJobProvider($failerFake, $eventsFake, $this->app['encrypter']);
    $failedJobProvider->setQueue($queue);
    $this->app[FailedJobProvider::class] = $failedJobProvider;

    $agent->pushJob();
    $job = $queue->pop();
    $job->fail();
    Str::createUuidsUsingSequence([Uuid::fromString('00dc709e-90c4-70c2-87c8-9b7127d20e8f')]);
    $failedJobProvider->log('cloud', 'default', json_encode(['payload' => 'here']), new RuntimeException('Whoops!'));
    Str::createUuidsNormally();
    $queue->pop();

    expect($eventsFake->emitted[1]['job_name'])->toBe('');
});

test('it emits released job events', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $job = $queue->pop();
    $job->release();
    $queue->pop();

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'started',
            'queue' => 'default',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'released',
            'queue' => 'default',
            'duration_ms' => 0,
        ],
    ]);
});

test('pop returns a cloud job built from the agent response', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob(['messageId' => 'message-id', 'body' => 'job-body']);

    $job = $queue->pop();

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
    expect($job->getRawBody())->toBe('job-body');
});

test('pop returns null when the agent has no job', function () {
    $this->fakeEvents();
    [$queue] = $this->fakeQueue();

    expect($queue->pop())->toBeNull();
});

test('pop receives directly from sqs when the agent is disabled', function () {
    // With the agent disabled (the default) the queue receives from SQS.
    Http::fake();
    $this->fakeEvents();
    [$queue, $client] = $this->mockedQueue();

    $client->shouldReceive('receiveMessage')->once()->andReturn(new Result([
        'Messages' => [[
            'MessageId' => 'message-id',
            'ReceiptHandle' => 'receipt-handle',
            'Body' => 'job-body',
            'Attributes' => ['ApproximateReceiveCount' => 1],
        ]],
    ]));

    $job = $queue->pop();

    expect($job)->toBeInstanceOf(SqsJob::class);
    expect($job)->not->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
    expect($job->getRawBody())->toBe('job-body');
    Http::assertNothingSent();
});

test('pop returns null when sqs has no message and the agent is disabled', function () {
    $this->fakeEvents();
    [$queue, $client] = $this->mockedQueue();

    $client->shouldReceive('receiveMessage')->once()->andReturn(new Result(['Messages' => null]));

    expect($queue->pop())->toBeNull();
});

test('deleting a job reports processed to the agent without touching sqs', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $pushed = $agent->pushJob();

    $job = $queue->pop();
    $job->delete();

    expect($job->isDeleted())->toBeTrue();
    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'processed'],
    ]);
});

test('failing a job reports processed exactly once', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $pushed = $agent->pushJob();

    $job = $queue->pop();
    $job->fail(new RuntimeException('Whoops!'));

    // fail() routes through delete(), so it reports a single "processed".
    expect($job->hasFailed())->toBeTrue();
    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'processed'],
    ]);
});

test('releasing a job reports released with the delay to the agent', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $pushed = $agent->pushJob();

    $job = $queue->pop();
    $job->release(30);

    expect($job->isReleased())->toBeTrue();
    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'released', 'delay' => 30],
    ]);
});

test('pop builds the job with the cloud connection name', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob();

    expect($queue->pop()->getConnectionName())->toBe('cloud');
});

test('pop uses the receive count supplied by the agent', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob(['attributes' => ['ApproximateReceiveCount' => '5']]);

    expect($queue->pop()->attempts())->toBe(5);
});

test('reporting an outcome omits the receipt handle when the agent does not supply one', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    // A non-string handle is treated as absent, so the result omits it and
    // the agent falls back to matching on the message id alone.
    $pushed = $agent->pushJob(['receiptHandle' => null]);

    $queue->pop()->delete();

    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'status' => 'processed'],
    ]);
});

test('pop tolerates a non string body from the agent', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $agent->pushJob(['body' => ['not' => 'a-string']]);

    $job = $queue->pop();

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getRawBody())->toBe('');
});

test('pop accepts a falsy but valid message id', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    // "0" is a valid, non-empty id that empty() would wrongly reject.
    $agent->pushJob(['messageId' => '0']);

    expect($queue->pop()->getJobId())->toBe('0');
});

test('a rejected result is reported only once', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $pushed = $agent->pushJob();

    $agent->resultStatus = 422;

    $job = $queue->pop();

    try {
        $job->delete();
        $this->fail('Expected the rejected report to throw a RequestException.');
    } catch (RequestException) {
        //
    }

    // The agent's rejection is authoritative for this message, so only
    // transient socket failures are retried - never the verdict itself.
    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'processed'],
    ]);
});

test('deleting propagates when the agent is unreachable', function () {
    $this->fakeEvents();
    $sqs = $this->mock(SqsClient::class);
    [$queue, $agent] = $this->fakeQueue($sqs);
    $agent->pushJob();

    $job = $queue->pop();

    // An unreachable agent propagates rather than deleting from SQS directly.
    $agent->resultUnreachable = true;
    $sqs->shouldNotReceive('deleteMessage');

    $this->expectException(AgentUnreachableException::class);

    $job->delete();
});

test('releasing propagates when the agent is unreachable', function () {
    $this->fakeEvents();
    $sqs = $this->mock(SqsClient::class);
    [$queue, $agent] = $this->fakeQueue($sqs);
    $agent->pushJob();

    $job = $queue->pop();

    // An unreachable agent propagates rather than resetting visibility on SQS.
    $agent->resultUnreachable = true;
    $sqs->shouldNotReceive('changeMessageVisibility');

    $this->expectException(AgentUnreachableException::class);

    $job->release(30);
});

test('reporting throws when the agent rejects the result', function () {
    $this->fakeEvents();
    $sqs = $this->mock(SqsClient::class);
    [$queue, $agent] = $this->fakeQueue($sqs);
    $agent->pushJob();

    $job = $queue->pop();

    // A client-error rejection is message-specific, so it propagates as a
    // RequestException for the worker to report rather than deleting from
    // SQS directly or restarting the pod.
    $agent->resultStatus = 422;
    $sqs->shouldNotReceive('deleteMessage');

    $this->expectException(RequestException::class);

    $job->delete();
});

test('reporting escalates when the agent returns a server error', function () {
    $this->fakeEvents();
    $sqs = $this->mock(SqsClient::class);
    [$queue, $agent] = $this->fakeQueue($sqs);
    $agent->pushJob();

    $job = $queue->pop();

    // A server error means the agent itself is wedged, so it escalates as
    // an unreachable fault to restart the pod rather than deleting from SQS.
    $agent->resultStatus = 500;
    $sqs->shouldNotReceive('deleteMessage');

    $this->expectException(AgentUnreachableException::class);

    $job->delete();
});

test('pop throws when the agent returns an error', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    // Drive the agent's own GET /next stub so the non-200 branch is hit;
    // a separate Http::fake() would be shadowed by the agent closure.
    $agent->nextResponse = Http::response('error', 500);

    // An error status means the agent cannot serve work, so it escalates
    // as an unrecoverable fault rather than idling and re-polling forever.
    $this->expectException(AgentUnreachableException::class);

    $queue->pop();
});

test('pop throws when the agent returns an unexpected success status', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    // The agent only ever answers 200 (job) or 204 (empty); any other 2xx
    // is off-contract, so it escalates rather than being decoded as a job.
    $agent->nextResponse = Http::response('', 202);

    $this->expectException(AgentUnreachableException::class);

    $queue->pop();
});

test('pop throws when the agent returns a non array body', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    // A 200 that decodes to a scalar is an agent fault; treat it the same
    // as an unreachable agent rather than idling.
    $agent->nextResponse = Http::response('"not-an-array"', 200);

    $this->expectException(AgentUnreachableException::class);

    $queue->pop();
});

test('pop throws when the agent socket is unreachable', function () {
    Sleep::fake();
    $this->fakeEvents();
    [$queue] = $this->fakeQueue();

    // An unreachable socket must escalate as an unrecoverable fault, not idle.
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->expectException(AgentUnreachableException::class);

    $queue->pop();
});

test('pop retries a timed out long poll immediately', function () {
    Sleep::fake();
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    // A worker that scaled to zero mid long-poll wakes with its request
    // deadline lapsed, so the poll times out even though the agent is
    // healthy. The poll is retried transparently - and without any backoff,
    // since the agent typically answers the fresh attempt at once.
    $agent->nextExceptions = [$this->pollTimeoutException()];
    $agent->pushJob(['messageId' => 'message-id', 'body' => 'job-body']);

    $job = $queue->pop();

    expect($job)->toBeInstanceOf(CloudJob::class);
    expect($job->getJobId())->toBe('message-id');
    expect($agent->nextRequests)->toBe(2);
    Sleep::assertNeverSlept();
});

test('pop throws when every long poll attempt times out', function () {
    Sleep::fake();
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    // Timeouts across the original attempt and both retries mean the agent is
    // live but wedged - the socket accepts, yet nothing answers - so it
    // escalates to restart the pod. The second retry backs off to give an
    // agent that is itself still waking a moment to recover.
    $agent->nextExceptions = [
        $this->pollTimeoutException(),
        $this->pollTimeoutException(),
        $this->pollTimeoutException(),
    ];

    try {
        $queue->pop();

        $this->fail('AgentUnreachableException was not thrown.');
    } catch (AgentUnreachableException) {
        expect($agent->nextRequests)->toBe(3);
        Sleep::assertSequence([Sleep::usleep(500_000)]);
    }
});

test('agent aware detector treats an unreachable agent as a lost connection', function () {
    $detector = new AgentAwareLostConnectionDetector(new LostConnectionDetector);

    // An unreachable agent is treated as a lost connection so the worker exits.
    expect($detector->causedByLostConnection(new AgentUnreachableException))->toBeTrue();

    // Everything else is delegated to the wrapped detector untouched.
    expect($detector->causedByLostConnection(new \RuntimeException('boom')))->toBeFalse();
    expect($detector->causedByLostConnection(new \RuntimeException('server has gone away')))->toBeTrue();
});

test('releasing then failing reports both outcomes to the agent', function () {
    $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();
    $pushed = $agent->pushJob();

    $job = $queue->pop();
    $job->release(30);
    $job->fail(new RuntimeException('Whoops!'));

    // CloudJob keeps no memory of prior reports, so both outcomes are
    // reported in order; reconciling them is the agent's responsibility.
    $this->assertAgentResults([
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'released', 'delay' => 30],
        ['messageId' => $pushed['messageId'], 'receiptHandle' => $pushed['receiptHandle'], 'status' => 'processed'],
    ]);
});

test('it emits job queued event', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    Cloud::configureManagedQueues($this->app);
    Cloud::bootManagedQueues($this->app);
    $eventsFake = $this->fakeEvents();
    [$queue, $client] = $this->mockedQueue();
    $client->shouldReceive('sendMessage')->times(7)->andReturn(new Result());

    $queue->push(new FakeJob, queue: '1');
    $queue->pushOn('2', new FakeJob);
    $queue->pushRaw('', queue: '3');
    $queue->later(1, new FakeJob, queue: '4');
    $queue->laterOn('5', 1, new FakeJob);
    $queue->bulk([new FakeJob, new FakeJob], queue: '6');

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '1',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '2',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '3',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '4',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '5',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '6',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '6',
        ],
    ]);
});

test('it emits released event when worker stops because it timed out', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        $agent->pushJob();
        $queue->pop();
        $this->travel(2)->seconds();

        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::TimedOut));

        expect($eventsFake->emitted)->toBe([
            [
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:05.060708',
                'type' => 'started',
                'queue' => 'default',
            ],
            [
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:07.060708',
                'type' => 'released',
                'queue' => 'default',
                'duration_ms' => 2000,
            ],
        ]);
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it emits processed event when worker stops for reasons other than timed out', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    $reasons = [
        WorkerStopReason::Interrupted,
        WorkerStopReason::LostConnection,
        WorkerStopReason::MaxJobsExceeded,
        WorkerStopReason::MaxMemoryExceeded,
        WorkerStopReason::MaxTimeExceeded,
        WorkerStopReason::QueueEmpty,
        WorkerStopReason::ReceivedRestartSignal,
    ];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        foreach ($reasons as $index => $reason) {
            $agent->pushJob();
            $queue->pop();

            $this->app['events']->dispatch(new WorkerStopping(0, null, $reason));

            expect($eventsFake->emitted[($index * 2) + 1])->toBe([
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:05.060708',
                'type' => 'processed',
                'queue' => 'default',
                'duration_ms' => 0,
            ]);
        }
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it emits processed event when worker stops without a reason', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        $agent->pushJob();
        $queue->pop();

        $this->app['events']->dispatch(new WorkerStopping);

        expect($eventsFake->emitted)->toBe([
            [
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:05.060708',
                'type' => 'started',
                'queue' => 'default',
            ],
            [
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:05.060708',
                'type' => 'processed',
                'queue' => 'default',
                'duration_ms' => 0,
            ],
        ]);
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('worker stopping listener emits failed type when processing job has failed', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        $agent->pushJob();
        $job = $queue->pop();
        $job->fail();

        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::TimedOut));

        expect($eventsFake->emitted[1]['type'])->toBe('failed');
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('worker stopping listener emits released type when processing job was released', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        $agent->pushJob();
        $job = $queue->pop();
        $job->release();

        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::MaxJobsExceeded));

        expect($eventsFake->emitted[1]['type'])->toBe('released');
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('worker stopping listener does nothing when no job is processing', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        $this->fakeQueue();

        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::TimedOut));
        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::QueueEmpty));

        expect($eventsFake->emitted)->toBe([]);
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it does not register worker stopping listener when not running queue work', function () {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', 'tinker'];

    try {
        $this->travelTo('2000-01-02 03:04:05.060708');
        Cloud::configureManagedQueues($this->app);
        Cloud::bootManagedQueues($this->app);
        $eventsFake = $this->fakeEvents();
        [$queue, $agent] = $this->fakeQueue();

        $agent->pushJob();
        $queue->pop();

        $this->app['events']->dispatch(new WorkerStopping(0, null, WorkerStopReason::TimedOut));

        expect($eventsFake->emitted)->toBe([
            [
                '_cloud_event' => 'queue',
                'timestamp' => '2000-01-02 03:04:05.060708',
                'type' => 'started',
                'queue' => 'default',
            ],
        ]);
    } finally {
        $_SERVER['argv'] = $argv;
    }
});

test('it respects dispatch after transaction', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    Cloud::configureManagedQueues($this->app);
    Cloud::bootManagedQueues($this->app);
    $eventsFake = $this->fakeEvents();
    $this->app['config']->set('queue.connections.cloud.connection.after_commit', true);
    [$queue, $client] = $this->mockedQueue();
    $client->shouldReceive('sendMessage')->times(7)->andReturn(new Result());

    DB::beginTransaction();

    $queue->push(new FakeJob, queue: '1');
    $queue->pushOn('2', new FakeJob);
    $queue->pushRaw('', queue: '3');
    $queue->later(1, new FakeJob, queue: '4');
    $queue->laterOn('5', 1, new FakeJob);
    $queue->bulk([new FakeJob, new FakeJob], queue: '6');

    $this->travel(10)->minutes();
    DB::commit();

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:04:05.060708',
            'type' => 'queued',
            'queue' => '3',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '1',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '2',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '4',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '5',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '6',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-02 03:14:05.060708',
            'type' => 'queued',
            'queue' => '6',
        ],
    ]);
});

test('it captures duration for multiple jobs', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $agent->pushJob();
    $queue->pop();
    $this->travel(1)->second();
    $queue->pop();
    $this->travel(0.5)->second();
    $queue->pop();

    expect($eventsFake->emitted[1]['duration_ms'])->toBe(1000);
    expect($eventsFake->emitted[3]['duration_ms'])->toBe(500);
});

test('it captures utc time', function () {
    date_default_timezone_set('Australia/Melbourne');
    $this->travelTo(Carbon::parse('2000-01-02 03:04:05.060708', 'Australia/Melbourne'));
    $eventsFake = $this->fakeEvents();
    [$queue, $agent] = $this->fakeQueue();

    $agent->pushJob();
    $queue->pop();
    $this->travel(1)->second();
    $queue->pop();

    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-01 16:04:05.060708',
            'type' => 'started',
            'queue' => 'default',
        ],
        [
            '_cloud_event' => 'queue',
            'timestamp' => '2000-01-01 16:04:06.060708',
            'type' => 'processed',
            'queue' => 'default',
            'duration_ms' => 1000,
        ],
    ]);
});

test('find proxies to failer for non urls', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    $job = $provider->find('not-a-url');

    expect($job)->toBeNull();
});

test('find gets url and decrypts response', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    $payload = ['id' => 'test-job-id', 'connection' => 'cloud', 'queue' => 'default', 'payload' => '{"job":"App\\\\Jobs\\\\TestJob"}'];
    $encrypted = Crypt::encryptString(json_encode($payload));

    Http::fake([
        'https://cloud.laravel.com/*' => Http::response($encrypted),
    ]);

    $result = $provider->find('https://cloud.laravel.com/api/jobs/test-job-id?signature=abc');

    expect($result)->toBeObject();
    expect($result->id)->toBe('test-job-id');
    expect($result->connection)->toBe('cloud');
    expect($result->queue)->toBe('default');
    expect($result->payload)->toBe('{"job":"App\\\\Jobs\\\\TestJob"}');
    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.laravel.com/api/jobs/test-job-id?signature=abc');
});

test('find returns null when decryption fails', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    Http::fake([
        'https://cloud.laravel.com/*' => Http::response('not-valid-encrypted-data'),
    ]);

    try {
        $provider->find('https://cloud.laravel.com/api/jobs/test-job-id?signature=abc');
        $this->fail();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(DecryptException::class);
    }
});

test('find returns null when http request fails', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    Http::fake([
        'https://cloud.laravel.com/*' => Http::response('Server Error', 500),
    ]);

    try {
        $provider->find('https://cloud.laravel.com/api/jobs/test-job-id?signature=abc');
        $this->fail();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(RequestException::class);
    }
});

test('forget proxies to failer for non urls', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    // First log a job to the failer with a UUID
    $uuid = (string) Str::uuid();
    $failer->log('database', 'default', json_encode(['uuid' => $uuid]), new \Exception('test'));
    $jobId = $failer->ids()[0];

    // Forget should delegate to the underlying failer
    $result = $provider->forget($jobId);

    expect($result)->toBeTrue();
    expect($failer->ids())->toBeEmpty();
});

test('forget emits event after find', function () {
    $this->travelTo('2000-01-02 03:04:05.060708');
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    $payload = ['id' => 'forget-test-id', 'connection' => 'cloud', 'queue' => 'default', 'payload' => '{}'];
    $encrypted = Crypt::encryptString(json_encode($payload));

    Http::fake([
        'https://cloud.laravel.com/*' => Http::response($encrypted),
    ]);

    $url = 'https://cloud.laravel.com/api/jobs/forget-test-id?signature=abc';
    $provider->find($url);
    $result = $provider->forget($url);

    expect($result)->toBeTrue();
    expect($eventsFake->emitted)->toBe([
        [
            '_cloud_event' => 'failed_job',
            'id' => 'forget-test-id',
            'queue' => 'default',
            'retried_at' => '2000-01-02 03:04:05.060708',
        ],
    ]);
});

test('forget returns false without prior find', function () {
    $eventsFake = $this->fakeEvents();
    $failer = $this->fakeFailer();
    $provider = new FailedJobProvider($failer, $eventsFake, $this->app['encrypter']);

    $result = $provider->forget('https://cloud.laravel.com/api/jobs/some-id?signature=abc');

    expect($result)->toBeFalse();
    expect($eventsFake->emitted)->toBeEmpty();
});

test('it throws managed queue not found exception when queue does not exist', function () {
    Cloud::configureManagedQueues($this->app);
    Cloud::bootManagedQueues($this->app);
    $this->fakeEvents();

    $mock = new MockHandler();
    $mock->append(fn (CommandInterface $cmd) => new AwsException('Queue does not exist.', $cmd, [
        'code' => 'AWS.SimpleQueueService.NonExistentQueue',
    ]));

    $client = new SqsClient([
        'region' => 'us-east-2',
        'version' => 'latest',
        'handler' => $mock,
        'credentials' => false,
    ]);

    $this->app->instance(QueueConnector::class, new QueueConnector(new class($client) implements ConnectorInterface
    {
        public function __construct(private $client)
        {
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

    $queue = $this->app['queue']->connection('cloud');

    $this->expectException(ManagedQueueNotFoundException::class);
    $this->expectExceptionMessage('Managed queue [missing-queue] does not exist.');

    $queue->push(new FakeJob, queue: 'missing-queue');
});

test('it uses config values to normalize queue name', function () {
    Cloud::configureManagedQueues($this->app);
    Cloud::bootManagedQueues($this->app);
    $eventsFake = $this->fakeEvents();
    [$queue, $client] = $this->mockedQueue();
    $client->shouldReceive('sendMessage')->times(1)->andReturn(new Result());

    unset($_SERVER['SQS_PREFIX'], $_SERVER['SQS_SUFFIX']);

    $queue->push(new FakeJob, queue: 'https://sqs.us-east-2.amazonaws.com/1234567/my-queue-env-8280cf2c-2081-47e8-a1f1-9cdfcba8618f');

    expect($eventsFake->emitted[0]['queue'])->toBe('my-queue');
});

test('it normalizes fifo queue names without leaking the suffix', function () {
    Cloud::configureManagedQueues($this->app);
    Cloud::bootManagedQueues($this->app);
    $eventsFake = $this->fakeEvents();
    [$queue, $client] = $this->mockedQueue();
    $client->shouldReceive('sendMessage')->times(1)->andReturn(new Result());

    $queue->push(new FakeJob, queue: 'orders.fifo');

    // The suffix is injected before ".fifo", so it must be stripped without
    // leaking into the normalized name.
    expect($eventsFake->emitted[0]['queue'])->toBe('orders.fifo');
});
