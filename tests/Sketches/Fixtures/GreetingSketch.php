<?php

namespace Venusian\Tests\Sketches\Fixtures;

use Voyager\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchLoopResult;

final class GreetingSketch extends Sketch
{
    protected string $description = 'Greets, then stops.';

    public function loop(array $mail = []): SketchLoopResult
    {
        $this->info('Hello from a sketch.');

        return SketchLoopResult::STOP;
    }
}
