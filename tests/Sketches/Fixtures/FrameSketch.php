<?php

namespace Venusian\Tests\Sketches\Fixtures;

use RuntimeException;
use Voyager\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchLoopResult;

/** Keeps every frame's mail, stops after $frames frames, and notes its boot and shutdowns. */
final class FrameSketch extends Sketch
{
    /** @var list<list<object>> */
    public array $frames = [];

    public bool $booted_before_first_frame = false;

    public int $shutdowns = 0;

    public function __construct(
        private readonly int $stop_after = PHP_INT_MAX,
        ?float $refresh_rate = null,
        private readonly bool $throws = false,
    ) {
        $this->refresh_rate = $refresh_rate;
    }

    public function boot(): void
    {
        $this->booted_before_first_frame = $this->frames === [];
    }

    public function loop(array $mail = []): SketchLoopResult
    {
        $this->frames[] = $mail;

        if ($this->throws) {
            throw new RuntimeException('frame failed');
        }

        return count($this->frames) >= $this->stop_after ? SketchLoopResult::STOP : SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        $this->shutdowns++;
    }
}
