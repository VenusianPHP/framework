<?php

use Voyager\Log\LogManager;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('writes a blocking info() through a configured channel', function () {
    $path = LogApp::channel($this->app, 'plain');

    $this->app->get('log')->channel('plain')->info('blocking {kind}', ['kind' => 'write']);

    expect(LogApp::lines($path)[0])->toContain('.INFO: blocking write');
});

it('ships no slack or papertrail channel', function () {
    expect($this->app['config']->get('logging.channels'))
        ->not->toHaveKey('slack')
        ->not->toHaveKey('papertrail');
});

it('has no deferred driver: a channel asking for one gets the emergency logger', function () {
    $emergency = LogApp::channel($this->app, 'emergency');
    $this->app['config']->set('logging.channels.buffered', ['driver' => 'deferred', 'channel' => 'single']);

    $this->app->get('log')->channel('buffered')->info('never buffered');

    expect(method_exists(LogManager::class, 'createDeferredDriver'))->toBeFalse()
        ->and(class_exists('Voyager\Log\DeferredFlush'))->toBeFalse()
        ->and(implode("\n", LogApp::lines($emergency)))->toContain('Driver [deferred] is not supported.');
});
