---
type: Module
title: Database testing helpers
description: Voyager\Database\Testing — RefreshDatabase, DatabaseTransactions, DatabaseMigrations, DatabaseTruncation and the database assertions, for any test case that meets the host contract.
tags: [database, testing, migrations, transactions]
---

# Overview

`Voyager\Database\Testing` carries Laravel v13.37.0's database test helpers, generic to any Venusian app. Each is a trait a test case uses.

* `RefreshDatabase` — `migrate:fresh` on the first test of the process, then a transaction per connection around each test, rolled back after it. A test that commits (or breaks) its transaction makes the next one migrate again. In-memory SQLite keeps its migrated PDO in `RefreshDatabaseState` and is handed to each test's new app.
* `DatabaseTransactions` — the transaction per test, no migrating.
* `DatabaseMigrations` — `migrate:fresh` before each test, `migrate:rollback` after it.
* `DatabaseTruncation` — `migrate:fresh` on the first test, then the tables emptied before each later one (the migrations table kept; `$tablesToTruncate`, `$exceptTables`, `$connectionsToTruncate`).
* `Concerns\InteractsWithDatabase` — `assertDatabaseHas/Missing/Count/Empty`, `assertModelExists/Missing`, `assertSoftDeleted/NotSoftDeleted`, `expectsDatabaseQueryCount()`, `castAsJson()`, `seed()`.
* `Concerns\CanConfigureMigrationCommands` — `$seed`, `$seeder`, `#[Seed]`, `#[Seeder]`, `$dropViews`, `$dropTypes`, `#[WithoutMigrationEvents]`.
* `DatabaseTransactionsManager` — the test's wrapping transaction is invisible to after-commit callbacks, as in Laravel.

Commands run through the console kernel (`Concerns\RunsDatabaseCommands`); a non-zero exit throws with the command's output.

# Host contract

A test case boots `$this->app` before each test and calls every used trait's `setUp{Trait}()` before it and `tearDown{Trait}()` after it. Laravel's `beforeApplicationDestroyed()` callbacks are those `tearDown{Trait}()` methods.

# Async code

Offloaded queries and `saveAsync()` run on a worker's own connection, outside a test's transaction: they cannot see its rows, and an async save refuses to run inside a transaction. Test async code with `DatabaseMigrations` or `DatabaseTruncation`, on a database file (a worker cannot reach in-memory SQLite).

# Tests

`tests/Database/Testing/DatabaseTestingTest.php` drives a host fixture (`tests/Database/Fixtures/TestingHost.php`) through several tests in a row: migrate once, re-migrate after a commit, in-memory across apps, after-commit callbacks, each trait, async under `DatabaseMigrations`, the assertions, the query count.
