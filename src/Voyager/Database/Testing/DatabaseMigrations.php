<?php

namespace Voyager\Database\Testing;

use Voyager\Database\Testing\Concerns\CanConfigureMigrationCommands;
use Voyager\Database\Testing\Concerns\RunsDatabaseCommands;

/**
 * Migrates the databases fresh before each test and rolls the migrations back after it. No
 * transaction wraps the test, so offloaded queries and async saves see its rows: on a database file,
 * since a worker cannot reach in-memory SQLite.
 *
 * The host test case boots `$this->app` before each test and calls `setUpDatabaseMigrations()` and
 * `tearDownDatabaseMigrations()` around it, as it does for every trait's `setUp{Trait}`/`tearDown{Trait}`.
 */
trait DatabaseMigrations
{
    use CanConfigureMigrationCommands, RunsDatabaseCommands;

    public function setUpDatabaseMigrations(): void
    {
        $this->runDatabaseMigrations();
    }

    public function tearDownDatabaseMigrations(): void
    {
        $this->runDatabaseCommand('migrate:rollback');

        RefreshDatabaseState::$migrated = false;
    }

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->runDatabaseCommand('migrate:fresh', $this->migrateFreshUsing());
        $this->afterRefreshingDatabase();
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
