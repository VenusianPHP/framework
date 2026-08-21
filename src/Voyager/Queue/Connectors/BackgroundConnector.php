<?php

namespace Voyager\Queue\Connectors;

use Voyager\Queue\BackgroundQueue;

class BackgroundConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @return \Voyager\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new BackgroundQueue($config['after_commit'] ?? null);
    }
}
