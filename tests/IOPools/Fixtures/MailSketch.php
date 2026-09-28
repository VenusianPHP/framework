<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\Sketches\Sketch;
use Voyager\Contracts\Sketches\SketchLoopResult;

/** Keeps every list of mail its loop() gets, and answers STOP once it has seen $stop_after pieces. */
final class MailSketch implements Sketch
{
    /** @var list<list<object>> */
    public array $loops = [];

    private int $seen = 0;

    public function __construct(private readonly int $stop_after = PHP_INT_MAX) {}

    public function boot(): void {}

    public function loop(array $mail = []): SketchLoopResult
    {
        $this->loops[] = $mail;
        $this->seen += count($mail);

        return $this->seen >= $this->stop_after ? SketchLoopResult::STOP : SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void {}

    public function refreshRate(): ?float
    {
        return null;
    }
}
