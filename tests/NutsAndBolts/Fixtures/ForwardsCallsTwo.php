<?php

namespace Tests\NutsAndBolts\Fixtures;

use Voyager\NutsAndBolts\Concerns\ForwardsCalls;

class ForwardsCallsTwo
{
    use ForwardsCalls;

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(new ForwardsCallsBase, $method, $parameters);
    }

    public function forwardedTwo(...$parameters)
    {
        return $parameters;
    }
}
