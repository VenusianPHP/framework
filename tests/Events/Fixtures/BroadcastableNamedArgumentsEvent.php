<?php

namespace Tests\Events\Fixtures;

class BroadcastableNamedArgumentsEvent
{
    use \Voyager\System\Events\Dispatchable;

    public function __construct(
        public string $first,
        public string $second,
    ) {
    }
}
