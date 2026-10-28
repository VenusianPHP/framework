<?php

namespace Voyager\NutsAndBolts\Concerns;

use Voyager\NutsAndBolts\DataObjects\HigherOrderTapProxy;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param (callable($this): mixed)|null $callback
     * @return $this|HigherOrderTapProxy
     */
    public function tap(?callable $callback = null): static|HigherOrderTapProxy
    {
        return tap($this, $callback);
    }
}