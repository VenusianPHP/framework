<?php

namespace Voyager\Broadcasting\Broadcasters;

class NullBroadcaster extends Broadcaster
{
    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        //
    }
}
