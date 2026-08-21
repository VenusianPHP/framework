<?php

namespace Tests\NutsAndBolts\Fixtures;

class ForwardsCallsBase
{
    public function forwardedBase(...$parameters)
    {
        return $parameters;
    }

    public function baseError()
    {
        return $this->missingMethod();
    }
}
