<?php

namespace Voyager\Broadcasting;

class FakePendingBroadcast extends PendingBroadcast
{
    /**
     * Create a new pending broadcast instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Broadcast the event using a specific broadcaster.
     *
     * @param  string|null  $connection
     * @return $this
     */
    public function via($connection = null)
    {
        return $this;
    }

    /**
     * Broadcast the event to everyone except the given socket.
     *
     * Pusher/Reverb-only exclusion; Redis only passes the field through.
     *
     * @param  string  $socket
     * @return $this
     */
    public function toOthers(string $socket)
    {
        return $this;
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        //
    }
}
