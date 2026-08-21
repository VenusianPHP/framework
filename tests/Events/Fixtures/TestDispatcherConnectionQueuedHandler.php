<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherConnectionQueuedHandler implements ShouldQueue
{
    public $connection = 'redis';

    public $delay = 10;

    public $queue = 'my_queue';

    public function handle()
    {
        //
    }
}
