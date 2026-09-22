<?php

namespace Voyager\Broadcasting;

use Voyager\Contracts\Signals\SignalDispatcher;

use function Voyager\NutsAndBolts\Helpers\enum_value;

class PendingBroadcast
{
    /**
     * The signal dispatcher implementation.
     *
     * @var \Voyager\Contracts\Signals\SignalDispatcher
     */
    protected $signals;

    /**
     * The event instance.
     *
     * @var mixed
     */
    protected $event;

    /**
     * Create a new pending broadcast instance.
     *
     * @param  \Voyager\Contracts\Signals\SignalDispatcher  $signals
     * @param  mixed  $event
     */
    public function __construct(SignalDispatcher $signals, $event)
    {
        $this->event = $event;
        $this->signals = $signals;
    }

    /**
     * Broadcast the event using a specific broadcaster.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return $this
     */
    public function via($connection = null)
    {
        if (method_exists($this->event, 'broadcastVia')) {
            $this->event->broadcastVia(enum_value($connection));
        }

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
        if (method_exists($this->event, 'dontBroadcastToCurrentUser')) {
            $this->event->dontBroadcastToCurrentUser($socket);
        }

        return $this;
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->signals->dispatch($this->event);
    }
}
