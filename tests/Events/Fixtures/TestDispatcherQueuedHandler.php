<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherQueuedHandler implements ShouldQueue
{
    public function handle()
    {
        //
    }
}
