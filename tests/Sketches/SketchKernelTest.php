<?php
declare(strict_types=1);

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Tests\Sketches\Fixtures\Sketches\PingSketch;
use Voyager\Contracts\Sketches\Kernel as KernelContract;
use Voyager\Core\Bootstrap\HandleExceptions;
use Voyager\Core\RenderedInstance;
use Voyager\Core\Sketches\Kernel;
use Voyager\Sketches\Signals\SketchFinished;
use Voyager\Sketches\Signals\SketchStarting;

afterEach(function () {
    HandleExceptions::flushState($this);
});

function sketchApp(): RenderedInstance
{
    $app = new RenderedInstance(__DIR__.'/Fixtures/app');
    $app->registerSingleton(KernelContract::class, Kernel::class);
    return $app;
}

test('runs a registered sketch by name', function () {
    $app = sketchApp();
    $kernel = $app->make(KernelContract::class)->addSketches([PingSketch::class]);
    $out = new BufferedOutput;
    expect($kernel->handle(new ArrayInput(['command' => 'ping-sketch']), $out))->toBe(0);
});

test('discovers sketches from a path', function () {
    $app = sketchApp();
    $kernel = $app->make(KernelContract::class)
        ->discoverUsing('Venusian\\Tests\\Sketches\\', __DIR__)
        ->addSketchPaths([__DIR__.'/Fixtures/Sketches']);
    $out = new BufferedOutput;
    $kernel->handle(new ArrayInput(['command' => 'list']), $out);
    expect($out->fetch())->toContain('ping-sketch')->toContain('custom-name');
});

test('no argument lists', function () {
    $app = sketchApp();
    $kernel = $app->make(KernelContract::class)->addSketches([PingSketch::class]);
    $out = new BufferedOutput;
    expect($kernel->handle(new ArrayInput([]), $out))->toBe(0)
        ->and($out->fetch())->toContain('ping-sketch');
});

test('signals fire around a run', function () {
    $app = sketchApp();
    $kernel = $app->make(KernelContract::class)->addSketches([PingSketch::class]);
    $kernel->bootstrap();
    $kernel->rerouteSymfonyCommandEvents();   // constructor skips it under runningUnitTests()
    $seen = [];
    $app['signals']->listen(SketchStarting::class, function (SketchStarting $s) use (&$seen) { $seen[] = 'start:'.$s->command; });
    $app['signals']->listen(SketchFinished::class, function (SketchFinished $s) use (&$seen) { $seen[] = 'finish:'.$s->exit_code; });
    $kernel->handle(new ArrayInput(['command' => 'ping-sketch']), new BufferedOutput);
    expect($seen)->toBe(['start:ping-sketch', 'finish:0']);
});

test('a throwing sketch renders and returns 1', function () {
    $app = sketchApp();
    $boom = new class extends \Voyager\Sketches\Sketch {
        public function loop(): \Voyager\Contracts\Sketches\SketchLoopResult { throw new RuntimeException('boom'); }
    };
    $kernel = $app->make(KernelContract::class)->addSketches([$boom::class]);
    $out = new BufferedOutput;
    expect($kernel->handle(new ArrayInput(['command' => \Voyager\Sketches\SketchRegistry::nameFor($boom::class)]), $out))->toBe(1)
        ->and($out->fetch())->toContain('boom');
});
