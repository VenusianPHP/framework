<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastEvent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return ['test-channel'];
    }

    public function broadcastWhen()
    {
        return true;
    }
}
