<?php

namespace Tests\Bus\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class BusDispatcherTestCustomQueueCommand implements ShouldQueue
{
    public function queue($queue, $command)
    {
        $queue->push($command);
    }
}
