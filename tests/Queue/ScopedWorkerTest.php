<?php

use Venusian\Tests\Queue\Fixtures\QueueApp;
use Venusian\Tests\Queue\Fixtures\ScopedProbeJob;

beforeEach(fn () => $this->app = QueueApp::boot());
afterEach(fn () => QueueApp::tearDown($this->app, $this));

/** Pushes two probe jobs and lets queue:work's daemon loop drain them. */
function workTwoProbes($app): array
{
    $queue = $app['queue']->connection('database');
    $queue->push(new ScopedProbeJob(QueueApp::journal(), 'first'));
    $queue->push(new ScopedProbeJob(QueueApp::journal(), 'second'));

    return QueueApp::run($app, ['command' => 'queue:work', 'connection' => 'database', '--stop-when-empty' => true]);
}

it('hands each job the worker runs a fresh scope, in the main context', function () {
    $this->app->scoped('probe', fn () => new ArrayObject);

    [$code] = workTwoProbes($this->app);

    expect($code)->toBe(0)
        ->and(QueueApp::lines())->toBe(['first saw 0', 'second saw 0']);
});

it('hands each job the worker runs a fresh scope, with the worker itself in a fiber', function () {
    $this->app->scoped('probe', fn () => new ArrayObject);
    $main = $this->app->make('probe');

    $fiber = new Fiber(fn () => workTwoProbes($this->app));
    $fiber->start();

    expect($fiber->isTerminated())->toBeTrue()
        ->and($fiber->getReturn()[0])->toBe(0)
        ->and(QueueApp::lines())->toBe(['first saw 0', 'second saw 0'])
        ->and(count($main))->toBe(0);
});
