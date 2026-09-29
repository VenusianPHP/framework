<?php

namespace Venusian\Tests\Sketches\Fixtures;

use Voyager\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Contracts\Sketches\Attributes\Sketch as SketchName;

#[SketchName('launch-pad')]
final class NamedSketch extends Sketch
{
    public function loop(array $mail = []): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
