<?php

namespace Voyager\Contracts\Filesystem;

interface Factory
{
    /**
     * Get a filesystem implementation.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Voyager\Contracts\Filesystem\Filesystem
     */
    public function disk(\UnitEnum|string|null $name = null);
}
