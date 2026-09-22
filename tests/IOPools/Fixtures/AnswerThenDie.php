<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;
use Voyager\IOPools\ProcessPoolFrame;

/**
 * Writes a complete reply frame itself, then exits before pool-worker gets to.
 * The parent sees the frame and EOF on the same tick.
 */
class AnswerThenDie implements ShouldPool
{
    public function __construct(
        public readonly mixed $answer = 42,
    ) {}

    public function handle(): mixed
    {
        ProcessPoolFrame::write(STDOUT, ['ok' => true, 'value' => $this->answer]);    // raw stream: skips ob_start

        exit(0);
    }
}
