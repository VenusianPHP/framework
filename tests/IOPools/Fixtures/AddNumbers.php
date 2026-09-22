<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;

class AddNumbers implements ShouldPool
{
    public function __construct(
        public readonly int $left,
        public readonly int $right,
    ) {}

    public function handle(): mixed
    {
        return $this->left + $this->right;
    }
}
