<?php

namespace Voyager\Contracts\IOPools;

use Voyager\IOPools\Event;

/**
 * The push-only face of an event queue. Producers get a sink and nothing
 * more — no reading, no draining. The one consumer is the loop that owns
 * the queue.
 */
interface EventSink
{
    public function push(Event $event): void;
}
