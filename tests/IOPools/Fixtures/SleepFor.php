<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;

class SleepFor implements ShouldPool
{
    public function __construct(
        public readonly float $seconds,
        public readonly mixed $result = null,
    ) {}

    public function handle(): mixed
    {
        usleep((int) ($this->seconds * 1_000_000));

        return $this->result ?? $this->seconds;
    }
}
