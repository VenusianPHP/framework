<?php

use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('fires a signal through signal(), answering with every listener or the first when halting', function () {
    $this->app['signals']->listen('greeted', fn (string $name) => "hello {$name}");
    $this->app['signals']->listen('greeted', fn (string $name) => "hi {$name}");

    expect(signal('greeted', ['ada']))->toBe(['hello ada', 'hi ada'])
        ->and(signal('greeted', ['ada'], halt: true))->toBe('hello ada');
});
