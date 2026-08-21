<?php

namespace Tests\NutsAndBolts\Fixtures;

use Voyager\NutsAndBolts\Concerns\ForwardsCalls;

class ForwardsCallsOne
{
    use ForwardsCalls;

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(new ForwardsCallsTwo, $method, $parameters);
    }

    public function throwTestException($method)
    {
        static::throwBadMethodCallException($method);
    }
}
