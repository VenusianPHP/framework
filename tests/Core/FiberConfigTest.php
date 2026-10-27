<?php

use Voyager\Config\Overrides;
use Voyager\Config\Repository;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** Writes $value to $key, suspends, then reads $key back: [read before suspend, read after]. */
function writeAcrossSuspend(string $key, mixed $value): Fiber
{
    return new Fiber(function () use ($key, $value): array {
        config([$key => $value]);
        $before = config($key);
        Fiber::suspend();

        return [$before, config($key)];
    });
}

it('keeps a write inside a fiber to that fiber, and the main context unchanged', function () {
    $base = config('app.name');

    $a = writeAcrossSuspend('app.name', 'a');
    $b = writeAcrossSuspend('app.name', 'b');
    $a->start();
    $b->start();
    $a->resume();
    $b->resume();

    expect($a->getReturn())->toBe(['a', 'a'])
        ->and($b->getReturn())->toBe(['b', 'b'])
        ->and(config('app.name'))->toBe($base)
        ->and($this->app['config']->all()['app']['name'])->toBe($base);
});

it('reads a nested write over the rest of its parent', function () {
    config(['probe' => ['kept' => 'base', 'changed' => 'base']]);

    $fiber = new Fiber(function (): array {
        config(['probe.changed' => 'fiber']);

        return [config('probe'), config()->has('probe.kept'), config()->getMany(['probe.changed', 'probe.kept'])];
    });
    $fiber->start();

    [$probe, $has, $many] = $fiber->getReturn();

    expect($probe)->toBe(['kept' => 'base', 'changed' => 'fiber'])
        ->and($has)->toBeTrue()
        ->and($many)->toBe(['probe.changed' => 'fiber', 'probe.kept' => 'base'])
        ->and(config('probe'))->toBe(['kept' => 'base', 'changed' => 'base']);
});

it('shows a main-context write through a fiber\'s overrides, built or not', function () {
    config(['probe' => ['main' => 'before', 'mine' => 'base']]);

    $fiber = new Fiber(function (): array {
        config(['probe.mine' => 'fiber']);
        $first = config('probe');
        Fiber::suspend();

        return [$first, config('probe')];
    });
    $fiber->start();

    config(['probe.main' => 'after']);
    $fiber->resume();

    [$first, $second] = $fiber->getReturn();

    expect($first)->toBe(['main' => 'before', 'mine' => 'fiber'])
        ->and($second)->toBe(['main' => 'after', 'mine' => 'fiber']);
});

it('builds a fiber\'s effective items once until a write changes them', function () {
    $fiber = new Fiber(function (): array {
        config(['probe.one' => 1]);

        foreach (range(1, 100) as $_) {
            config('probe.one');
            config('app.name');
        }

        $overrides = app(Overrides::class);
        $after_reads = $overrides->builds();

        config(['probe.two' => 2]);
        config('probe.two');
        config('probe.two');

        return [$after_reads, $overrides->builds()];
    });
    $fiber->start();

    expect($fiber->getReturn())->toBe([1, 2]);
});

it('pushes and prepends inside a fiber without touching the main context', function () {
    config(['probe.list' => ['b']]);

    $fiber = new Fiber(function (): array {
        config()->push('probe.list', 'c');
        config()->prepend('probe.list', 'a');

        return config('probe.list');
    });
    $fiber->start();

    expect($fiber->getReturn())->toBe(['a', 'b', 'c'])
        ->and(config('probe.list'))->toBe(['b']);
});

it('runs a loop task with the overrides of the fiber that started it', function () {
    $loop = $this->app->get('event-loop');

    $starter = new Fiber(function () use ($loop): array {
        config(['app.name' => 'starter']);
        $task = $loop->async(fn () => config('app.name'));

        return [config('app.name'), $loop->await($task)];
    });
    $starter->start();
    $loop->until(fn () => $starter->isTerminated());

    expect($starter->getReturn())->toBe(['starter', 'starter'])
        ->and(config('app.name'))->not->toBe('starter');
});

it('lets the overrides go with their fibers', function () {
    for ($i = 0; $i < 1000; $i++) {
        $fiber = new Fiber(fn () => config(['probe.n' => $i]));
        $fiber->start();
    }

    unset($fiber);
    gc_collect_cycles();

    $scopes = (new ReflectionProperty(\Voyager\Vessel\ControlPanel::class, 'fiber_scopes'))->getValue($this->app);

    expect(count($scopes ?? []))->toBe(0)
        ->and(config('probe'))->toBeNull();
});

it('keeps the repository global with no resolver', function () {
    $config = new Repository(['a' => 1]);

    $fiber = new Fiber(fn () => $config->set('a', 2));
    $fiber->start();

    expect($config->get('a'))->toBe(2);
});
