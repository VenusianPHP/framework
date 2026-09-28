<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** Ends the worker mid-gig, the way a fatal error or a stray exit() would. */
final class ExitGig implements ShouldPool
{
    public function handle(): mixed
    {
        // A thread has no STDERR constant; a process does.
        if (defined('STDERR')) {
            fwrite(STDERR, "leaving now\n");
        }

        exit(3);
    }
}
