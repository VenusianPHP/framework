---
type: Module
title: Database
description: Illuminate toolkit. Eloquent lives under Instrument. Bindings behind app('db'). No facade. On the loop via via() and stream().
resource: src/Voyager/Database/DatabaseServiceProvider.php
tags: [database, instrument, sqlite, migrations]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.7/cursor, at: 2026-09-22T22:10:00Z }
sources:
  - id: provider
    resource: src/Voyager/Database/DatabaseServiceProvider.php
    title: DatabaseServiceProvider
  - id: migrations
    resource: src/Voyager/Database/MigrationServiceProvider.php
    title: MigrationServiceProvider
  - id: events
    resource: src/Voyager/Database/Instrument/Concerns/HasEvents.php
    title: Model withoutEvents
  - id: config
    resource: config/database.php
    title: database config
  - id: stubs
    resource: src/Voyager/Database/Migrations/stubs/migration.create.stub
    title: migration create stub
---

# Overview

Illuminate database toolkit. Eloquent lives under `Voyager\Database\Instrument`. The model class is still `Model`.[^provider]

Bindings: `db`, `db.factory`, `db.connection`, `db.schema`, `db.transactions`, `migrator`, `migration.repository`, `migration.creator`. `db` is a lazy singleton. Boot with the sqlite default and no `DB_DATABASE` does not open a file.[^provider][^config]

Dispatcher is `Contracts\Signals\SignalDispatcher`. `Model::withoutEvents()` wraps it in `Signals\NullDispatcher`. `listen` and `forget` still reach the real dispatcher. `dispatch` is swallowed.[^events]

No facades. Calls go through `app('db')`. Migration stubs use `app('db.schema')`.[^stubs]

Drivers: sqlite, mysql, mariadb, pgsql, sqlsrv. The suite's default connection is sqlite. Some unit tests build mysql config and exception messages without opening MySQL.[^config]

`DatabaseQueryExceptionTest`, `DatabaseSchemaBuilderIntegrationTest`, `DatabaseInstrumentIntegrationTest`, `DatabaseSQLiteBuilderTest`, `DatabaseMigratorIntegrationTest`, and `tests/Database/migrations/` sit in `tests/Database/deferred/`. They drive `DB::` / `Schema::`. 0.9 has no `MagicAliases`. Restore them with Testing.

`MigrationServiceProvider` is deferred. It registers the migrator, repository, creator, `migrate:*`, and `make:migration`. A migrator with a null dispatcher does not fatal.[^migrations]

On the loop: [Database on the loop](loop.md). `via()` promises, `stream()` mail.

[^provider]: DatabaseServiceProvider
[^migrations]: MigrationServiceProvider
[^events]: Model withoutEvents
[^config]: database config
[^stubs]: migration create stub
