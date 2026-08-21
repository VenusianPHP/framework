<?php

namespace Voyager\Contracts\NutsAndBolts;

interface DeferrableProvider
{
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides();
}
