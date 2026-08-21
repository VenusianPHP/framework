<?php

namespace Voyager\Contracts\Redis;

interface Factory
{
    /**
     * Get a Redis connection by name.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Voyager\Redis\Connections\Connection
     */
    public function connection($name = null);
}
