<?php

use Voyager\Vessel\ControlPanel;
use Voyager\Log\Context\Repository;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Vessel\Fixtures\ScopedByAttribute;
use Venusian\Tests\Vessel\Fixtures\BoundScopedConcrete;
use Venusian\Tests\Vessel\Fixtures\BoundScopedContract;

use function Voyager\NutsAndBolts\defer;

/** The per-fiber bags the vessel is holding right now. */
function fiberScopes(ControlPanel $vessel): int
{
    return count((new ReflectionProperty(ControlPanel::class, 'fiber_scopes'))->getValue($vessel) ?? []);
}

/** Resolves $abstract, suspends, resolves it again: [first, second]. */
function resolveAcrossSuspend(ControlPanel $vessel, string $abstract): Fiber
{
    return new Fiber(function () use ($vessel, $abstract): array {
        $first = $vessel->make($abstract);
        Fiber::suspend();

        return [$first, $vessel->make($abstract)];
    });
}

it('gives two interleaved fibers instances of their own, each kept across its suspends', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);

    $a = resolveAcrossSuspend($vessel, 'probe');
    $b = resolveAcrossSuspend($vessel, 'probe');

    $a->start();
    $b->start();
    $a->resume();
    $b->resume();

    [$a_first, $a_second] = $a->getReturn();
    [$b_first, $b_second] = $b->getReturn();

    expect($a_second)->toBe($a_first)
        ->and($b_second)->toBe($b_first)
        ->and($a_first)->not->toBe($b_first)
        ->and($vessel->make('probe'))->not->toBe($a_first)->not->toBe($b_first);
});

it('keeps one instance for the main context, outside any fiber', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);

    expect($vessel->make('probe'))->toBe($vessel->make('probe'))
        ->and($vessel->isResolved('probe'))->toBeTrue();
});

it('shares a plain singleton across the main context and every fiber', function () {
    $vessel = new ControlPanel;
    $vessel->registerSingleton('shared', fn () => new stdClass);

    $a = resolveAcrossSuspend($vessel, 'shared');
    $b = resolveAcrossSuspend($vessel, 'shared');
    $a->start();
    $b->start();
    $a->resume();
    $b->resume();

    $main = $vessel->make('shared');

    expect($a->getReturn())->each->toBe($main)
        ->and($b->getReturn())->each->toBe($main);
});

it('forgets the scoped instances of the current fiber only', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);
    $main_before = $vessel->make('probe');

    $fiber = new Fiber(function () use ($vessel): array {
        $before = $vessel->make('probe');
        Fiber::suspend();
        $kept = $vessel->make('probe');
        $vessel->forgetScopedInstances();

        return [$before, $kept, $vessel->make('probe')];
    });

    $fiber->start();
    $vessel->forgetScopedInstances();
    $main_after = $vessel->make('probe');
    $fiber->resume();

    [$before, $kept, $after] = $fiber->getReturn();

    expect($main_after)->not->toBe($main_before)
        ->and($kept)->toBe($before)
        ->and($after)->not->toBe($before)
        ->and($vessel->make('probe'))->toBe($main_after);
});

it('lets the bags go with their fibers, finished or abandoned mid-suspend', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);

    for ($i = 0; $i < 1000; $i++) {
        $fiber = new Fiber(fn () => $vessel->make('probe'));
        $fiber->start();
    }

    $abandoned = new Fiber(function () use ($vessel): void {
        $vessel->make('probe');
        Fiber::suspend();
    });
    $abandoned->start();

    // a bag that holds its own fiber is still let go
    $cyclic = new Fiber(function () use ($vessel): void {
        $vessel->registerInstance('probe', Fiber::getCurrent());
    });
    $cyclic->start();

    unset($fiber, $abandoned, $cyclic);
    gc_collect_cycles();

    expect(fiberScopes($vessel))->toBe(0);
});

it('puts an instance registered for a scoped abstract into the current fiber only', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('unit', fn () => new stdClass);

    $register = fn (string $name) => new Fiber(function () use ($vessel, $name): array {
        $vessel->registerInstance('unit', $mine = (object) ['name' => $name]);
        Fiber::suspend();

        return [$mine, $vessel->make('unit'), $vessel->isResolved('unit')];
    });

    $a = $register('a');
    $b = $register('b');
    $a->start();
    $b->start();
    $a->resume();
    $b->resume();

    [$a_registered, $a_resolved, $a_is_resolved] = $a->getReturn();
    [$b_registered, $b_resolved] = $b->getReturn();

    expect($a_resolved)->toBe($a_registered)
        ->and($b_resolved)->toBe($b_registered)
        ->and($a_is_resolved)->toBeTrue()
        ->and($vessel->make('unit'))->not->toBe($a_registered)->not->toBe($b_registered);
});

it('drops every scope\'s instance when a scoped abstract is bound again', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);
    $vessel->make('probe');

    $fiber = resolveAcrossSuspend($vessel, 'probe');
    $fiber->start();

    $vessel->scoped('probe', fn () => new ArrayObject);
    $fiber->resume();

    [$before, $after] = $fiber->getReturn();

    expect($before)->toBeInstanceOf(stdClass::class)
        ->and($after)->toBeInstanceOf(ArrayObject::class)
        ->and($vessel->make('probe'))->toBeInstanceOf(ArrayObject::class);
});

it('stops scoping an abstract bound again as a singleton', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);
    $vessel->registerSingleton('probe', fn () => new stdClass);

    $fiber = resolveAcrossSuspend($vessel, 'probe');
    $fiber->start();
    $fiber->resume();

    expect($fiber->getReturn())->each->toBe($vessel->make('probe'));
});

it('scopes the return types of a closure registered as scoped', function () {
    $vessel = new ControlPanel;
    $vessel->scoped(fn (): ArrayObject => new ArrayObject);

    $fiber = resolveAcrossSuspend($vessel, ArrayObject::class);
    $fiber->start();
    $fiber->resume();

    [$first, $second] = $fiber->getReturn();

    expect($second)->toBe($first)
        ->and($vessel->make(ArrayObject::class))->not->toBe($first);
});

it('scopes a class marked #[Scoped] per fiber, without any binding', function () {
    $vessel = new ControlPanel;

    $fiber = resolveAcrossSuspend($vessel, ScopedByAttribute::class);
    $fiber->start();
    $main = $vessel->make(ScopedByAttribute::class);
    $fiber->resume();

    [$first, $second] = $fiber->getReturn();

    expect($second)->toBe($first)
        ->and($main)->not->toBe($first)
        ->and($vessel->make(ScopedByAttribute::class))->toBe($main);
});

it('scopes a contract bound with #[Bind] and #[Scoped] per fiber', function () {
    $vessel = new ControlPanel;
    $vessel->resolveEnvironmentUsing(fn () => true);

    $main = $vessel->make(BoundScopedContract::class);
    $fiber = resolveAcrossSuspend($vessel, BoundScopedContract::class);
    $fiber->start();
    $fiber->resume();

    [$first, $second] = $fiber->getReturn();

    expect($main)->toBeInstanceOf(BoundScopedConcrete::class)
        ->and($second)->toBe($first)
        ->and($first)->not->toBe($main);
});

it('forgets an unset scoped abstract in every scope', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);
    $vessel->make('probe');

    $fiber = new Fiber(function () use ($vessel): bool {
        $vessel->make('probe');
        Fiber::suspend();

        return $vessel->isResolved('probe') || $vessel->isBound('probe');
    });
    $fiber->start();

    unset($vessel['probe']);
    $fiber->resume();

    expect($fiber->getReturn())->toBeFalse()
        ->and($vessel->isResolved('probe'))->toBeFalse();
});

it('empties every scope on flush', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);
    $main = $vessel->make('probe');

    $fiber = resolveAcrossSuspend($vessel, 'probe');
    $fiber->start();

    $vessel->flush();
    $vessel->scoped('probe', fn () => new stdClass);
    $fiber->resume();

    [$before, $after] = $fiber->getReturn();

    expect($after)->not->toBe($before)
        ->and($vessel->make('probe'))->not->toBe($main);
});

it('shares the current scope with a fiber not yet started', function () {
    $vessel = new ControlPanel;
    $vessel->scoped('probe', fn () => new stdClass);

    $parent = new Fiber(function () use ($vessel): array {
        $mine = $vessel->make('probe');

        $child = new Fiber(fn () => $vessel->make('probe'));
        $vessel->shareScopeWith($child);
        $child->start();

        return [$mine, $child->getReturn()];
    });
    $parent->start();

    [$mine, $childs] = $parent->getReturn();

    expect($childs)->toBe($mine)
        ->and($vessel->make('probe'))->not->toBe($mine);
});

it('refuses to share a scope with a fiber already started', function () {
    $vessel = new ControlPanel;
    $fiber = new Fiber(fn () => Fiber::suspend());
    $fiber->start();

    expect(fn () => $vessel->shareScopeWith($fiber))->toThrow(LogicException::class);
});

describe('from the caller\'s side', function () {
    beforeEach(fn () => $this->app = LogApp::boot());
    afterEach(fn () => LogApp::tearDown($this->app, $this));

    it('keeps the log context one fiber adds out of another fiber\'s log lines', function () {
        $path = LogApp::channel($this->app, 'probe');
        $log = $this->app->get('log')->channel('probe');

        $unit = fn (string $id) => new Fiber(function () use ($id, $log): array {
            $this->app->make(Repository::class)->add('unit_id', $id);
            Fiber::suspend();
            $log->info("handled {$id}");

            return $this->app->make(Repository::class)->all();
        });

        $a = $unit('a');
        $b = $unit('b');
        $a->start();
        $b->start();
        $a->resume();
        $b->resume();

        $lines = LogApp::lines($path);

        expect($a->getReturn())->toBe(['unit_id' => 'a'])
            ->and($b->getReturn())->toBe(['unit_id' => 'b'])
            ->and($this->app->make(Repository::class)->all())->toBe([])
            ->and($lines[0])->toContain('handled a')->toContain('"unit_id":"a"')->not->toContain('"unit_id":"b"')
            ->and($lines[1])->toContain('handled b')->toContain('"unit_id":"b"')->not->toContain('"unit_id":"a"');
    });

    it('collects the callbacks a fiber defers in that fiber\'s collection', function () {
        $fiber = new Fiber(function (): DeferredCallbackCollection {
            defer(fn () => null, 'in-fiber');

            return $this->app->make(DeferredCallbackCollection::class);
        });
        $fiber->start();

        $mains = $this->app->make(DeferredCallbackCollection::class);

        expect($fiber->getReturn())->not->toBe($mains)
            ->and(count($fiber->getReturn()))->toBe(1)
            ->and(count($mains))->toBe(0);
    });
});
