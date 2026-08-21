<?php

namespace Voyager\Contracts\Broadcasting;

interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Voyager\Broadcasting\Channel|\Voyager\Broadcasting\Channel[]|string[]|string
     */
    public function broadcastOn();
}
