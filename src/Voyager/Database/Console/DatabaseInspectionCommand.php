<?php

namespace Voyager\Database\Console;

use Voyager\Console\Command;
use Voyager\Database\ConnectionInterface;
use Voyager\NutsAndBolts\DataObjects\Arr;

abstract class DatabaseInspectionCommand extends Command
{
    /**
     * Get a human-readable name for the given connection.
     *
     * @param  \Voyager\Database\ConnectionInterface  $connection
     * @param  string  $database
     * @return string
     *
     * @deprecated
     */
    protected function getConnectionName(ConnectionInterface $connection, $database)
    {
        return $connection->getDriverTitle();
    }

    /**
     * Get the number of open connections for a database.
     *
     * @param  \Voyager\Database\ConnectionInterface  $connection
     * @return int|null
     *
     * @deprecated
     */
    protected function getConnectionCount(ConnectionInterface $connection)
    {
        return $connection->threadCount();
    }

    /**
     * Get the connection configuration details for the given connection.
     *
     * @param  string|null  $database
     * @return array
     */
    protected function getConfigFromDatabase($database)
    {
        $database ??= config('database.default');

        return Arr::except(config('database.connections.'.$database), ['password']);
    }
}
