<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Core\Queue\Queueable;

class FakeSqsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        //
    }
}
