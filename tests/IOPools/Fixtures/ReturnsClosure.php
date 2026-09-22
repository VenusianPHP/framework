<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;

/** Its answer can't be serialized, so it can't leave the worker. */
class ReturnsClosure implements ShouldPool
{
    public function handle(): mixed
    {
        return fn () => 'never arrives';
    }
}
