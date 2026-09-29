<?php

use Voyager\Broadcasting\BroadcastManager;
use Voyager\Broadcasting\FakePendingBroadcast;
use Voyager\Broadcasting\PendingBroadcast;

if (! function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     *
     * @param  mixed  $event
     */
    function broadcast(mixed $event = null): PendingBroadcast
    {
        return app(BroadcastManager::class)->event($event);
    }
}

if (! function_exists('broadcast_if')) {
    /**
     * Begin broadcasting an event if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  mixed  $event
     */
    function broadcast_if(bool $boolean, mixed $event = null): PendingBroadcast
    {
        if ($boolean) {
            return app(BroadcastManager::class)->event(value($event));
        }

        return new FakePendingBroadcast;
    }
}
