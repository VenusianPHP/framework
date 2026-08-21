<?php

namespace Tests\Events\Fixtures;

class DispatchableNamedArgumentsEvent
{
    use \Voyager\System\Events\Dispatchable;

    public function __construct(
        public string $first,
        public string $second,
    ) {
    }
}
