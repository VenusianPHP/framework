<?php

use Psr\Log\NullLogger;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('joins Monolog channels into a stack that writes through each of them', function () {
    $first = LogApp::channel($this->app, 'first');
    $second = LogApp::channel($this->app, 'second');
    $this->app['config']->set('logging.channels.both', ['driver' => 'stack', 'channels' => ['first', 'second']]);

    $this->app->get('log')->channel('both')->info('to both');

    expect(LogApp::lines($first)[0])->toContain('.INFO: to both')
        ->and(LogApp::lines($second)[0])->toContain('.INFO: to both');
});

it('names the channel when a stack member is not a Monolog logger', function () {
    $emergency = LogApp::channel($this->app, 'emergency');
    $this->app['config']->set('logging.channels.psr', ['driver' => 'custom', 'via' => fn () => new NullLogger]);
    $this->app['config']->set('logging.channels.mixed', ['driver' => 'stack', 'channels' => ['psr']]);

    $this->app->get('log')->channel('mixed')->info('never stacked');

    expect(implode("\n", LogApp::lines($emergency)))
        ->toContain('Log [psr] cannot join a stack: stack channels must be Monolog loggers.');
});
