<?php

namespace Venusian\Tests\IOPools\Fixtures;

use RuntimeException;
use Voyager\Contracts\IOPools\ShouldPool;

class ExplodingJob implements ShouldPool
{
    public function __construct(
        public readonly string $message = 'nope',
    ) {}

    public function handle(): mixed
    {
        throw new RuntimeException($this->message);
    }
}
