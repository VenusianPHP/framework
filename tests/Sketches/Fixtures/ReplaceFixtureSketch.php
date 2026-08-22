<?php

namespace Tests\Sketches\Fixtures;

use Voyager\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

#[SketchAttribute('replace-fixture')]
class ReplaceFixtureSketch extends Sketch
{
    public function loop(): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
