<?php

namespace Voyager\Queue\Connectors;

use Voyager\Queue\DatabaseQueue;
use Voyager\Database\ConnectionResolverInterface;

class DatabaseConnector implements ConnectorInterface
{
    public function __construct(protected ConnectionResolverInterface $connections) {}

    /**
     * @return \Voyager\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new DatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'] ?? 'default',
            (int) ($config['retry_after'] ?? 60),
            (bool) ($config['after_commit'] ?? false),
        );
    }
}
