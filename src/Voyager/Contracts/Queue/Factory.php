<?php

namespace Voyager\Contracts\Queue;

interface Factory
{
    /**
     * Resolve a queue connection instance.
     *
     * @param  string|null  $name
     * @return \Voyager\Contracts\Queue\Queue
     */
    public function connection($name = null);
}
