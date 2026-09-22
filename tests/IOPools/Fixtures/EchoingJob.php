<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\ShouldPool;

class EchoingJob implements ShouldPool
{
    public function handle(): mixed
    {
        echo 'noise from a gig';

        return 'clean';
    }
}
