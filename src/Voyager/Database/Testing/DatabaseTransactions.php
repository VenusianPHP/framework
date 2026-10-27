<?php

namespace Voyager\Database\Testing;

/**
 * Wraps each test in a transaction per connection, rolled back after it.
 *
 * The host test case boots `$this->app` before each test and calls `setUpDatabaseTransactions()` and
 * `tearDownDatabaseTransactions()` around it, as it does for every trait's `setUp{Trait}`/`tearDown{Trait}`.
 *
 * Offloaded queries and async saves run on a worker's own connection, outside the transaction: they
 * cannot see its rows, and an async save refuses to run inside it. Test async code with
 * DatabaseMigrations or DatabaseTruncation on a database file.
 */
trait DatabaseTransactions
{
    public function setUpDatabaseTransactions(): void
    {
        $this->beginDatabaseTransaction();
    }

    public function tearDownDatabaseTransactions(): void
    {
        $this->rollBackDatabaseTransaction();
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

            $dispatcher = $connection->getEventDispatcher();
            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }
    }

    /**
     * Roll back each connection's transaction, without firing events, and disconnect it.
     */
    protected function rollBackDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);

            $dispatcher = $connection->getEventDispatcher();
            $connection->unsetEventDispatcher();
            $connection->rollBack();
            $connection->setEventDispatcher($dispatcher);
            $connection->disconnect();
        }
    }

    /**
     * The connections to wrap: the test's $connectionsToTransact, or the default connection.
     *
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact
            : [null];
    }
}
