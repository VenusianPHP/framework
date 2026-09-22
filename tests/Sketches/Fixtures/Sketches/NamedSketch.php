<?php
declare(strict_types=1);
namespace Venusian\Tests\Sketches\Fixtures\Sketches;

use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

#[\Voyager\Contracts\Sketches\Attributes\Sketch('custom-name')]
class NamedSketch extends Sketch
{
    public function loop(): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
