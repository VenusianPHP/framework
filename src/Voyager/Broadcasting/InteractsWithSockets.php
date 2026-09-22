<?php

namespace Voyager\Broadcasting;

trait InteractsWithSockets
{
    /**
     * The socket ID for the connection that raised the event.
     */
    public ?string $socket = null;

    /**
     * Exclude one connection; a device knows its own socket id from its Pusher/Reverb client.
     */
    public function dontBroadcastToCurrentUser(string $socket): static
    {
        $this->socket = $socket;

        return $this;
    }

    /**
     * Broadcast the event to everyone.
     */
    public function broadcastToEveryone(): static
    {
        $this->socket = null;

        return $this;
    }
}
