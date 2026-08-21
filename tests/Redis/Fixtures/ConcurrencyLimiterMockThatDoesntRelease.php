<?php

namespace Tests\Redis\Fixtures;

use Voyager\Redis\Limiters\ConcurrencyLimiter;

class ConcurrencyLimiterMockThatDoesntRelease extends ConcurrencyLimiter
{
    protected function release($key, $id)
    {
        //
    }
}
