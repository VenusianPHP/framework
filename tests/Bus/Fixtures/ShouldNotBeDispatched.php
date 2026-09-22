<?php

namespace Venusian\Tests\Bus\Fixtures;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Queue\InteractsWithQueue;
use RuntimeException;

class ShouldNotBeDispatched implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle(): mixed
    {
        throw new RuntimeException('This should not be run');
    }
}
