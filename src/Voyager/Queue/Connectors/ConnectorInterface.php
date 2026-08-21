<?php

namespace Voyager\Queue\Connectors;

interface ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Voyager\Contracts\Queue\Queue
     */
    public function connect(array $config);
}
