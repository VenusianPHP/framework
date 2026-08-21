<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Broadcasting\ShouldBroadcast;

class AlwaysBroadcastEvent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return ['test-channel'];
    }
}
