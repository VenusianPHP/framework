<?php

namespace Voyager\Contracts\Broadcasting;

use Voyager\Broadcasting\Channel;

interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|Channel[]|string[]|string
     */
    public function broadcastOn(): array|Channel|string;
}
