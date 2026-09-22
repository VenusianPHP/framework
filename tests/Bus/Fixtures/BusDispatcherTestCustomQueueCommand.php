<?php

namespace Venusian\Tests\Bus\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class BusDispatcherTestCustomQueueCommand implements ShouldQueue
{
    public function handle(): mixed
    {
        return null;
    }

    public function queue($queue, $command)
    {
        $queue->push($command);
    }
}
