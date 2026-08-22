<?php

namespace Tests\Sketches\Fixtures;

use Voyager\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

#[SketchAttribute('attributed-fixture')]
class AttributedFixtureSketch extends Sketch
{
    public function loop(): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
