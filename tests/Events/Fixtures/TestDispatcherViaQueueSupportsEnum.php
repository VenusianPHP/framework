<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherViaQueueSupportsEnum implements ShouldQueue
{
    public function viaQueue()
    {
        return TestQueueType::EnumeratedQueue;
    }
}
