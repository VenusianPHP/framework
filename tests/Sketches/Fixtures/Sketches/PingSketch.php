<?php
declare(strict_types=1);
namespace Venusian\Tests\Sketches\Fixtures\Sketches;

use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

class PingSketch extends Sketch
{
    protected string $description = 'Ping once.';

    public function loop(): SketchLoopResult
    {
        return SketchLoopResult::STOP;
    }
}
