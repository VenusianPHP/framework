<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches;

interface Sketch
{
    public function boot(): void;
    public function loop(): SketchLoopResult;
    public function shutdown(): void;
    /** Hz. Null = use config('sketches.refresh_rate'). */
    public function refreshRate(): ?float;
}
