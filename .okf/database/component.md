---
type: Module
title: Database
description: Illuminate toolkit. Eloquent lives under Instrument. Bindings behind app('db'). No facade. Offloaded with via() and stream().
resource: src/Voyager/Database/DatabaseServiceProvider.php
tags: [database, instrument, sqlite, migrations]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
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

Illuminate database toolkit. Eloquent lives under `Voyager\Database\Instrument`; the model class is still `Model`. `Model` implements `QueueableEntity` and `HasBroadcastChannel`; `Instrument\Collection` implements `QueueableCollection`.[^provider]

Bindings: `db`, `db.factory`, `db.connection`, `db.schema`, `db.transactions`, `migrator`, `migration.repository`, `migration.creator`. Aliases: `db` is `DatabaseManager` and `ConnectionResolverInterface`; `db.connection` is `Connection` and `ConnectionInterface`; `db.schema` is `Schema\Builder`.[^provider]

Config `config/database.php`: `default` (`DB_CONNECTION`, `sqlite`), connections sqlite, mysql, mariadb, pgsql, sqlsrv, neo4j, `migrations`.[^config]

Dispatcher is `Contracts\Signals\SignalDispatcher`. `Model::withoutEvents()` wraps it in `Signals\NullDispatcher`: `listen` and `forget` reach the real dispatcher, `dispatch` is swallowed.[^events]

No facades. Calls go through `app('db')`. Migration stubs use `app('db.schema')`.[^stubs]

`MigrationServiceProvider` is deferred: migrator, repository, creator, `migrate:*`, `make:migration`. A migrator with a null dispatcher does not fatal.[^migrations]

Queries block. Off the loop's thread: [Database on the loop](loop.md).

[^provider]: DatabaseServiceProvider
[^migrations]: MigrationServiceProvider
[^events]: Model withoutEvents
[^config]: database config
[^stubs]: migration create stub
