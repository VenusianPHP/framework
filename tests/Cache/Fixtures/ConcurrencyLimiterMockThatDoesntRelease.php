<?php

namespace Tests\Cache\Fixtures;

use Voyager\Cache\Limiters\ConcurrencyLimiter;

/** A ConcurrencyLimiter whose release() is a no-op, so its lock never lets go on its own. */
class ConcurrencyLimiterMockThatDoesntRelease extends ConcurrencyLimiter
{
    protected function release($lock)
    {
        //
    }
}
