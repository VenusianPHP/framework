<?php

use Voyager\Contracts\Sketches\SketchExitStatus;
use Voyager\Pipeline\Pipeline;
use Voyager\Sketches\Middleware\DispatchSketch;
use Voyager\Sketches\SketchRunContext;
use Voyager\Sketches\SketchRunner;
use Tests\Sketches\Fixtures\CountingSketch;
use Tests\Sketches\Fixtures\RecordingMiddleware;

test('dispatch sketch runs middleware around the runner', function () {
    RecordingMiddleware::$calls = [];

    $sketch = new CountingSketch(1);
    $runner = new SketchRunner;
    $pipeline = new Pipeline;
    $context = new SketchRunContext(
        name: 'counting',
        sketch: $sketch,
        runner: $runner,
    );

    $status = (new DispatchSketch($pipeline, $runner, [new RecordingMiddleware]))->run($context);

    expect($status)->toBe(SketchExitStatus::SUCCESS->value)
        ->and(RecordingMiddleware::$calls)->toBe(['before:counting', 'after:counting'])
        ->and($sketch->calls)->toBe(['boot', 'loop', 'shutdown']);
});
