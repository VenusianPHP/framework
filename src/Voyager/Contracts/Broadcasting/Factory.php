<?php

namespace Voyager\Contracts\Broadcasting;

interface Factory
{
    /**
     * Get a broadcaster implementation by name.
     *
     * @param  string|null  $name
     * @return \Voyager\Contracts\Broadcasting\Broadcaster
     */
    public function connection($name = null);
}
