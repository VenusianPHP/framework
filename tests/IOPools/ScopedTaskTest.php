<?php

use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('runs a task with the scope of the fiber that started it', function () {
    $this->app->scoped('unit', fn () => new stdClass);
    $loop = $this->app->get('event-loop');

    $unit = new Fiber(function () use ($loop): array {
        $mine = $this->app->make('unit');
        $task = $loop->async(fn () => $this->app->make('unit'));

        return [$mine, $loop->await($task)];
    });
    $unit->start();
    $loop->until(fn () => $unit->isTerminated());

    [$mine, $tasks] = $unit->getReturn();

    expect($tasks)->toBe($mine)
        ->and($this->app->make('unit'))->not->toBe($mine);
});

it('keeps two fibers\' tasks apart', function () {
    $this->app->scoped('unit', fn () => new stdClass);
    $loop = $this->app->get('event-loop');

    $unit = fn () => new Fiber(function () use ($loop): array {
        $mine = $this->app->make('unit');
        $task = $loop->async(function () use ($loop) {
            $loop->await($loop->defer(fn () => null));   // suspend mid-task so the two interleave

            return $this->app->make('unit');
        });

        return [$mine, $loop->await($task)];
    });

    [$a, $b] = [$unit(), $unit()];
    $a->start();
    $b->start();
    $loop->until(fn () => $a->isTerminated() && $b->isTerminated());

    expect($a->getReturn()[1])->toBe($a->getReturn()[0])
        ->and($b->getReturn()[1])->toBe($b->getReturn()[0])
        ->and($a->getReturn()[0])->not->toBe($b->getReturn()[0]);
});

it('gives a task started from the main context the main scope', function () {
    $this->app->scoped('unit', fn () => new stdClass);
    $loop = $this->app->get('event-loop');

    expect($loop->await($loop->async(fn () => $this->app->make('unit'))))
        ->toBe($this->app->make('unit'));
});
