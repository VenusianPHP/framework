<?php

use Voyager\System\Application;
use Voyager\Queue\Console\ListFailedCommand;
use Voyager\Queue\Failed\FailedJobProviderInterface;
use Mockery as m;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Build a fake failed_jobs row with an encoded JSON payload.
 *
 * @param  array  $payload
 * @return array
 */
function fakeFailedJob(array $payload): array
{
    return [
        'id' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode($payload + ['uuid' => 'fake-uuid']),
        'exception' => 'Exception: boom',
        'failed_at' => '2026-01-01 00:00:00',
    ];
}

/**
 * Build a row with a raw (possibly malformed) payload string.
 *
 * @param  string  $rawPayload
 * @return array
 */
function rawFailedJob(string $rawPayload): array
{
    return [
        'id' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => $rawPayload,
        'exception' => 'Exception: boom',
        'failed_at' => '2026-01-01 00:00:00',
    ];
}

/**
 * Wire up a stub failer, run the command, and return the buffered output.
 *
 * @param  array  $rows
 * @return string
 */
function executeCommand(array $rows): string
{
    $container = new Application;

    // The command resolves the failer via the queue.failer container binding.
    $failer = m::mock(FailedJobProviderInterface::class);
    $failer->shouldReceive('all')->andReturn($rows);
    $container->instance('queue.failer', $failer);

    $command = new ListFailedCommand;
    $command->setVenusian($container);

    $output = new BufferedOutput;
    $command->run(new ArrayInput([]), $output);

    return $output->fetch();
}

/**
 * Run queue:failed with the given failed_jobs rows and return captured output.
 *
 * @param  array  $rows
 * @return string
 */
function runListFailedCommandWith(array $rows): string
{
    return executeCommand($rows);
}

/**
 * Run queue:failed with a single raw-payload row and return captured output.
 *
 * @param  string  $rawPayload
 * @return string
 */
function runListFailedCommandWithRawPayload(string $rawPayload): string
{
    return executeCommand([rawFailedJob($rawPayload)]);
}

test('queued listener shows underlying listener class not wrapper', function () {
    // CallQueuedListener is the wrapper class Laravel uses to dispatch
    // queued event listeners. The legacy regex matched the wrapper out
    // of data.command — payload.displayName carries the actual listener.
    $output = runListFailedCommandWith([
        fakeFailedJob([
            'displayName' => 'App\\Listeners\\HandleStripeWebhookHandled',
            'data' => [
                'commandName' => 'Voyager\\Events\\CallQueuedListener',
                'command' => 'O:42:"Voyager\\Events\\CallQueuedListener":3:{s:5:"class";s:43:"App\\Listeners\\HandleStripeWebhookHandled";s:6:"method";s:6:"handle";s:4:"data";a:0:{}}',
            ],
        ]),
    ]);

    expect($output)->toContain('App\\Listeners\\HandleStripeWebhookHandled')
        ->not->toContain('CallQueuedListener');
});

test('regular queued job still shows job class', function () {
    // Regression: the common ShouldQueue path must keep showing the job class.
    $output = runListFailedCommandWith([
        fakeFailedJob([
            'displayName' => 'App\\Jobs\\ProcessPodcast',
            'data' => [
                'commandName' => 'App\\Jobs\\ProcessPodcast',
                'command' => 'O:25:"App\\Jobs\\ProcessPodcast":0:{}',
            ],
        ]),
    ]);

    expect($output)->toContain('App\\Jobs\\ProcessPodcast');
});

test('legacy payload without display name falls back to regex', function () {
    // Pre-5.6 failed_jobs rows have no displayName. The legacy regex path
    // must still extract the first quoted class from data.command.
    $output = runListFailedCommandWith([
        fakeFailedJob([
            'data' => [
                'commandName' => 'App\\Jobs\\LegacyJob',
                'command' => 'O:18:"App\\Jobs\\LegacyJob":0:{}',
            ],
        ]),
    ]);

    expect($output)->toContain('App\\Jobs\\LegacyJob');
});

test('encrypted job shows underlying class', function () {
    // Encrypted queue payloads store ciphertext in data.command, so the
    // legacy regex falls back to CallQueuedHandler@call (the value at
    // payload.job). displayName carries the real underlying class.
    $output = runListFailedCommandWith([
        fakeFailedJob([
            'displayName' => 'App\\Jobs\\ProcessOrder',
            'job' => 'Voyager\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => 'Voyager\\Queue\\CallEncryptedQueuedHandler',
                'command' => 'eyJpdiI6IlhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFg9IiwidmFsdWUiOiJjaXBoZXJ0ZXh0LWJsb2Itd2l0aC1uby1jbGFzcy1uYW1lcyIsIm1hYyI6ImZha2UifQ==',
            ],
        ]),
    ]);

    expect($output)->toContain('App\\Jobs\\ProcessOrder')
        ->not->toContain('CallQueuedHandler');
});

test('malformed payload does not throw', function () {
    // Malformed JSON in the payload column must not bubble up an exception;
    // the row should render with an empty Class cell.
    $output = runListFailedCommandWithRawPayload('not-json-at-all');

    expect($output)->toContain('1');
});
