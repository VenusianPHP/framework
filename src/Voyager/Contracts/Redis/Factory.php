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

    /**
     * Commands on a socket of their own that don't block the loop, each answered by a promise.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return \Voyager\Redis\Sockets\RedisPipe
     */
    public function pipe(\UnitEnum|string|null $connection = null): \Voyager\Redis\Sockets\RedisPipe;
}
