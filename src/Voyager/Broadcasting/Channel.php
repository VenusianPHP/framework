<?php

namespace Voyager\Broadcasting;

use Stringable;
use Voyager\Contracts\Broadcasting\HasBroadcastChannel;

class Channel implements Stringable
{
    /**
     * The channel's name.
     *
     * @var string
     */
    public string|HasBroadcastChannel $name;

    /**
     * Create a new channel instance.
     *
     * @param \Voyager\Contracts\Broadcasting\HasBroadcastChannel|string $name
     */
    public function __construct(HasBroadcastChannel|string $name)
    {
        $this->name = $name instanceof HasBroadcastChannel ? $name->broadcastChannel() : $name;
    }

    /**
     * Convert the channel instance to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
