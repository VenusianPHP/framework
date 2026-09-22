<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Closure;
use Voyager\Contracts\IOPools\ShouldPool;

/** Can't be serialized, so it can't reach a worker. */
class HoldsClosure implements ShouldPool
{
    public function __construct(public readonly Closure $callback) {}

    public function handle(): mixed
    {
        return ($this->callback)();
    }
}
