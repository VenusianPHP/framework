<?php

namespace Tests\Events\Fixtures;

class BroadcastFalseCondition extends BroadcastEvent
{
    public function broadcastWhen()
    {
        return false;
    }
}
