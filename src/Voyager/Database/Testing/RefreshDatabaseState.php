<?php

namespace Voyager\Database\Testing;

/**
 * What RefreshDatabase keeps across the tests of one process.
 */
class RefreshDatabaseState
{
    /**
     * The PDO of each in-memory connection, handed to every new app's connection.
     *
     * @var array<string, \PDO>
     */
    public static array $inMemoryConnections = [];

    /**
     * Whether the databases have been migrated in this process.
     */
    public static bool $migrated = false;
}
