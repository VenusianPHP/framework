<?php
declare(strict_types=1);

use Voyager\Config\Repository;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\IOPools\EventLoop;
use Voyager\Sketches\Sketch;
use Voyager\Sketches\SketchRunner;

function countingSketch(int $stop_after, ?float $hz = null, ?callable $on_tick = null): Sketch
{
    return new class($stop_after, $hz, $on_tick) extends Sketch {
        public int $ticks = 0;
        public int $boots = 0;
        public int $shutdowns = 0;
        public function __construct(private int $stop_after, ?float $hz, private mixed $on_tick) { $this->refresh_rate = $hz; }
        public function boot(): void { $this->boots++; }
        public function loop(): SketchLoopResult
        {
            $this->ticks++;
            if ($this->on_tick) { ($this->on_tick)($this); }
            return $this->ticks >= $this->stop_after ? SketchLoopResult::STOP : SketchLoopResult::CONTINUE;
        }
        public function shutdown(): void { $this->shutdowns++; }
    };
}

test('ticks until STOP, boots and shuts down once, exits 0', function () {
    $runner = new SketchRunner(new EventLoop, new Repository(['sketches' => ['refresh_rate' => 1000]]));
    $sketch = countingSketch(5);
    expect($runner->run($sketch))->toBe(0)
        ->and($sketch->ticks)->toBe(5)
        ->and($sketch->boots)->toBe(1)
        ->and($sketch->shutdowns)->toBe(1);
});

test('sketch refresh rate overrides config for the session', function () {
    $config = new Repository(['sketches' => ['refresh_rate' => 60]]);
    (new SketchRunner(new EventLoop, $config))->run(countingSketch(1, 500.0));
    expect($config->get('sketches.refresh_rate'))->toBe(500.0);
});

test('null refresh rate defers to config', function () {
    $config = new Repository(['sketches' => ['refresh_rate' => 250]]);
    (new SketchRunner(new EventLoop, $config))->run(countingSketch(1));
    expect($config->get('sketches.refresh_rate'))->toBe(250);
});

test('config change mid-run changes the period', function () {
    $config = new Repository(['sketches' => ['refresh_rate' => 5]]);   // 200ms per tick
    $sketch = countingSketch(6, null, function ($s) use ($config) {
        if ($s->ticks === 1) { $config->set('sketches.refresh_rate', 1000); }
    });
    $start = microtime(true);
    (new SketchRunner(new EventLoop, $config))->run($sketch);
    // 5 ticks at 200ms would be ~1s; at 1000Hz they take well under 100ms
    expect(microtime(true) - $start)->toBeLessThan(0.5);
});

test('loop throw shuts down once and rethrows', function () {
    $sketch = countingSketch(99, 1000.0, fn ($s) => throw new RuntimeException('tick died'));
    $runner = new SketchRunner(new EventLoop, new Repository(['sketches' => ['refresh_rate' => 1000]]));
    expect(fn () => $runner->run($sketch))->toThrow(RuntimeException::class, 'tick died');
    expect($sketch->shutdowns)->toBe(1);
});

test('boot throw shuts down once', function () {
    $sketch = new class extends Sketch {
        public int $shutdowns = 0;
        public function boot(): void { throw new LogicException('no boot'); }
        public function loop(): SketchLoopResult { return SketchLoopResult::STOP; }
        public function shutdown(): void { $this->shutdowns++; }
    };
    $runner = new SketchRunner(new EventLoop, new Repository(['sketches' => ['refresh_rate' => 1000]]));
    expect(fn () => $runner->run($sketch))->toThrow(LogicException::class);
    expect($sketch->shutdowns)->toBe(1);
});

test('zero refresh rate clamps', function () {
    $runner = new SketchRunner(new EventLoop, new Repository(['sketches' => ['refresh_rate' => 0]]));
    $start = microtime(true);
    $runner->run(countingSketch(3));
    expect(microtime(true) - $start)->toBeLessThan(4.0);   // floor is 1 Hz: 3 arms ≈ 3s, not a hang or a fault
});

test('stop() from outside ends the run with the given status', function () {
    $loop = new EventLoop;
    $runner = new SketchRunner($loop, new Repository(['sketches' => ['refresh_rate' => 1000]]));
    $loop->at(0.01, fn () => $runner->stop(7));
    $sketch = countingSketch(PHP_INT_MAX);
    expect($runner->run($sketch))->toBe(7)
        ->and($sketch->shutdowns)->toBe(1);
});
