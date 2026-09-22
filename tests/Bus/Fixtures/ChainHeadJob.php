<?php

namespace Venusian\Tests\Bus\Fixtures;

use Voyager\Bus\Batchable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Core\Bus\Dispatchable;
use Voyager\Bus\Queueable;

class ChainHeadJob implements ShouldQueue
{
    public function handle(): mixed
    {
        return null;
    }

    use Batchable, Dispatchable, Queueable;
}
