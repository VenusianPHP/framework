<?php

namespace Voyager\Broadcasting\Broadcasters;

class NullBroadcaster extends Broadcaster
{

    // Laravel authenticates an incoming request for a channel here.
    // Receiving requests is out of scope for this port, so the auth surface
    // is cut and only the broadcast path remains.

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        //
    }
}
