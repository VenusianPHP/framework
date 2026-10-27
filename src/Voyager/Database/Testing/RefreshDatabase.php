<?php

namespace Voyager\Database\Testing;

use Voyager\Database\Testing\Concerns\CanConfigureMigrationCommands;
use Voyager\Database\Testing\Concerns\RunsDatabaseCommands;

/**
 * Migrates the databases fresh once per process, then wraps each test in a transaction per
 * connection, rolled back after it. In-memory SQLite keeps its migrated PDO across the tests' apps.
 *
 * The host test case boots `$this->app` before each test and calls `setUpRefreshDatabase()` and
 * `tearDownRefreshDatabase()` around it, as it does for every trait's `setUp{Trait}`/`tearDown{Trait}`.
 *
 * Offloaded queries and async saves run on a worker's own connection, outside the transaction: they
 * cannot see its rows, and an async save refuses to run inside it. Test async code with
 * DatabaseMigrations or DatabaseTruncation on a database file.
 */
trait RefreshDatabase
{
    use CanConfigureMigrationCommands, RunsDatabaseCommands;

    public function setUpRefreshDatabase(): void
    {
        $this->refreshDatabase();
    }

    public function tearDownRefreshDatabase(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();

            // The test committed or broke its transaction: what it wrote stays, so migrate again next time.
            if ($connection->getPdo() && ! $connection->getPdo()->inTransaction()) {
                RefreshDatabaseState::$migrated = false;
            }

            $connection->rollBack(0);
            $connection->setEventDispatcher($dispatcher);
            $connection->disconnect();
        }
    }

    /**
     * Migrate if this process has not, then begin the test's transactions.
     */
    public function refreshDatabase(): void
    {
        $this->beforeRefreshingDatabase();

        if ($this->usingInMemoryDatabases()) {
            $this->restoreInMemoryDatabase();
        }

        $this->refreshTestDatabase();

        $this->afterRefreshingDatabase();
    }

    protected function usingInMemoryDatabases(): bool
    {
        foreach ($this->connectionsToTransact() as $name) {
            if ($this->usingInMemoryDatabase($name)) {
                return true;
            }
        }

        return false;
    }

    protected function usingInMemoryDatabase(?string $name = null): bool
    {
        $config = $this->app->make('config');
        $name ??= $config->get('database.default');

        return $config->get("database.connections.{$name}.database") === ':memory:';
    }

    /**
     * Hand each in-memory connection of this test's app the PDO the migrations ran on.
     */
    protected function restoreInMemoryDatabase(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            if (isset(RefreshDatabaseState::$inMemoryConnections[$name])) {
                $database->connection($name)->setPdo(RefreshDatabaseState::$inMemoryConnections[$name]);
            }
        }
    }

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->shouldDispatchMigrationEvents()
                ? $this->migrateDatabases()
                : $this->migrateDatabasesWithoutEvents();

            $this->updateLocalCacheOfInMemoryDatabases();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function updateLocalCacheOfInMemoryDatabases(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            if ($this->usingInMemoryDatabase($name)) {
                RefreshDatabaseState::$inMemoryConnections[$name] = $database->connection($name)->getPdo();
            }
        }
    }

    protected function migrateDatabases(): void
    {
        $this->runDatabaseCommand('migrate:fresh', $this->migrateFreshUsing());
    }

    protected function migrateDatabasesWithoutEvents(): void
    {
        $database = $this->app->make('db');
        $dispatchers = [];

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $dispatchers[$name] = $connection->getEventDispatcher();
            $connection->unsetEventDispatcher();
        }

        try {
            $this->migrateDatabases();
        } finally {
            foreach ($dispatchers as $name => $dispatcher) {
                if ($dispatcher) {
                    $database->connection($name)->setEventDispatcher($dispatcher);
                }
            }
        }
    }

    /**
     * Begin a transaction on each connection, under the testing transactions manager, without firing events.
     */
    public function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');
        $connections = $this->connectionsToTransact();

        $this->app->registerInstance('db.transactions', $manager = new DatabaseTransactionsManager($connections));

        foreach ($connections as $name) {
            $connection = $database->connection($name);
            $connection->setTransactionManager($manager);

            if ($this->usingInMemoryDatabase($name)) {
                RefreshDatabaseState::$inMemoryConnections[$name] ??= $connection->getPdo();
            }

            $dispatcher = $connection->getEventDispatcher();
            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }
    }

    /**
     * The connections to migrate and wrap: the test's $connectionsToTransact, or the default connection.
     *
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact
            : [$this->app->make('config')->get('database.default')];
    }

    protected function beforeRefreshingDatabase(): void
    {
        //
    }

    protected function afterRefreshingDatabase(): void
    {
        //
    }
}
