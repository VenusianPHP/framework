<?php
declare(strict_types=1);
namespace Voyager\Contracts\Sketches;

interface Sketch
{
    public function boot(): void;
    /** @param list<object> $mail what the loop delivered since the last frame; empty when nothing did */
    public function loop(array $mail = []): SketchLoopResult;
    public function shutdown(): void;
    /** Hz. Null = use config('sketches.refresh_rate'). */
    public function refreshRate(): ?float;
}
