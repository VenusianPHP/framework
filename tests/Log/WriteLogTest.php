<?php

use Voyager\Log\WriteLog;
use Voyager\Log\FlattenedThrowable;
use Voyager\Log\Context\Repository as ContextRepository;
use Monolog\JsonSerializableDateTimeImmutable;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Log\Fixtures\Caught;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** One call as AsyncWrites queues it. */
function call(string $message, array $context = [], ?array $snapshot = null): array
{
    return [
        'level' => 'info',
        'message' => $message,
        'context' => FlattenedThrowable::flatten($context),
        'datetime' => new JsonSerializableDateTimeImmutable(true),
        'snapshot' => $snapshot,
    ];
}

it('writes its calls through the channel, in order, as the blocking call would', function () {
    $path = LogApp::channel($this->app, 'gig');

    $written = new WriteLog('gig', ['gig' => $this->app['config']->get('logging.channels.gig')], [
        call('first {n}', ['n' => 1]),
        call('second'),
    ])->handle();

    $lines = LogApp::lines($path);

    expect($written)->toBe(2)
        ->and($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('.INFO: first 1')
        ->and($lines[1])->toContain('.INFO: second');
});

it('formats a rebuilt exception in the context as the original', function () {
    $path = LogApp::channel($this->app, 'gig');
    $e = Caught::underClosure();

    unserialize(serialize(new WriteLog('gig', ['gig' => $this->app['config']->get('logging.channels.gig')], [
        call('Payment failed', ['exception' => $e]),
    ])))->handle();

    expect(implode("\n", LogApp::lines($path)))
        ->toContain('Payment failed {"exception":"[object] (DomainException(code: 402): card declined at '.$e->getFile().':'.$e->getLine().')');
});

it('logs each call in the Context it was made in, then puts this process\'s Context back', function () {
    $path = LogApp::channel($this->app, 'gig');
    $context = $this->app->get(ContextRepository::class);

    $context->add('request_id', 'caller-own');
    $snapshot = new ContextRepository($this->app['signals'])->add('request_id', 'r-42')->dehydrate();

    new WriteLog('gig', ['gig' => $this->app['config']->get('logging.channels.gig')], [call('started', [], $snapshot)])->handle();

    expect(LogApp::lines($path)[0])->toContain('"request_id":"r-42"')
        ->and($context->get('request_id'))->toBe('caller-own');
});

it('rebuilds a channel whose config changed since this process last built it', function () {
    $old = LogApp::channel($this->app, 'gig');
    $this->app->get('log')->channel('gig')->info('to the old file');
    $new_config = [...$this->app['config']->get('logging.channels.gig'), 'path' => $old.'.moved'];

    new WriteLog('gig', ['gig' => $new_config], [call('to the new file')])->handle();

    expect(LogApp::lines($old))->toHaveCount(1)
        ->and(LogApp::lines($old.'.moved')[0])->toContain('to the new file');

    unlink($old.'.moved');
});
