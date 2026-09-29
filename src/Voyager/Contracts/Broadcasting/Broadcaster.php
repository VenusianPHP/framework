<?php

namespace Voyager\Contracts\Broadcasting;

interface Broadcaster
{
    // Laravel authenticates an incoming request for a channel here.
    // Receiving requests is out of scope for this port, so the auth surface
    // is cut and only the broadcast path remains.

    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param string $event
     * @param  array  $payload
     * @return void
     *
     * @throws BroadcastException
     */
    public function broadcast(array $channels, string $event, array $payload = []): void;
}
