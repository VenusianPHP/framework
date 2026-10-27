<?php

use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->loop = $this->app->get('event-loop');
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('resumes a task waiting on a promise resolved but not yet flushed', function () {
    $promise = $this->loop->promise();
    $promise->resolve('x');

    expect($promise->settled())->toBeFalse()
        ->and($this->loop->await($this->loop->async(fn () => $promise->wait())))->toBe('x');
});

it('resumes every task whose wait the flush satisfies, and only then gives up on the rest', function () {
    $resolved = $this->loop->promise();
    $resolved->resolve('ready');
    $never = $this->loop->promise();

    $ready = $this->loop->async(fn () => $resolved->wait());
    $stuck = $this->loop->async(fn () => $never->wait());

    expect($this->loop->await($ready))->toBe('ready')
        ->and(fn () => $this->loop->await($stuck))->toThrow(Voyager\Contracts\IOPools\CancelledException::class);
});
