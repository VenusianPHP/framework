<?php

namespace Voyager\Contracts\NutsAndBolts;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \Voyager\Contracts\NutsAndBolts\Htmlable|string
     */
    public function resolveDisplayableValue();
}
