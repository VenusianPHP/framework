<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetQueueDynamically implements ShouldQueue
{
    public $queue = 'my_queue';

    public function handle()
    {
        //
    }

    public function viaQueue($event)
    {
        if ($event['useHighPriorityQueue']) {
            return 'p0';
        }

        return 'p99';
    }
}
