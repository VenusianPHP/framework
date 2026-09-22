<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;

/**
 * Kills the worker mid-gig. Echoes first so the DeadWorkerException has a stderr tail to quote.
 */
class ExitingJob implements ShouldPool
{
    public function __construct(
        public readonly string $last_words = 'goodbye cruel world',
        public readonly int $code = 1,
    ) {}

    public function handle(): mixed
    {
        echo $this->last_words;

        exit($this->code);
    }
}
