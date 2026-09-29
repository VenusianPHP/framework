<?php

namespace Venusian\Tests\Sketches\Fixtures\Discovered;

use Voyager\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchLoopResult;

final class FoundSketch extends Sketch
{
    public function loop(array $mail = []): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
