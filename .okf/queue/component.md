---
type: Module
title: Queue
description: Queue manager with sync, database, redis, background, deferred, failover and null drivers; queue:work; failed jobs; table migrations.
resource: src/Voyager/Queue/QueueServiceProvider.php
tags: [queue, jobs, worker]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: provider
    resource: src/Voyager/Queue/QueueServiceProvider.php
    title: QueueServiceProvider
  - id: config
    resource: config/queue.php
    title: queue config
  - id: database
    resource: src/Voyager/Queue/DatabaseQueue.php
    title: DatabaseQueue
  - id: background
    resource: src/Voyager/Queue/BackgroundQueue.php
    title: BackgroundQueue
  - id: deferred
    resource: src/Voyager/Queue/DeferredQueue.php
    title: DeferredQueue
  - id: work
    resource: src/Voyager/Queue/Console/WorkCommand.php
    title: WorkCommand
  - id: context
    resource: src/Voyager/Log/ContextServiceProvider.php
    title: ContextServiceProvider
  - id: handleable
    resource: src/Voyager/Contracts/NutsAndBolts/Handleable.php
    title: Handleable
---

# Overview

Bindings `queue` (`QueueManager`, `Queue\Factory`, `Queue\Monitor`), `queue.connection`, `queue.worker`, `queue.listener`, `queue.failer` (`FailedJobProviderInterface`). Deferred provider. `dispatch($job)` goes through the bus. No `Queue` facade.[^provider]

A job implements `ShouldQueue`, which extends `Handleable::handle(): mixed`: `handle()` takes no required parameters and returns a value. `Voyager\Core\Queue\Queueable` bundles `Dispatchable`, `InteractsWithQueue`, the bus `Queueable`, `SerializesModels`.[^handleable]

Default connection `sync` (`QUEUE_CONNECTION`). Beanstalkd and SQS are not supported.[^config]

| Driver | Push |
|---|---|
| sync | runs now, here |
| database | row in `table` (`jobs`) on `connection` |
| redis | Redis lists, `block_for` optional |
| background | `BackgroundPush` gig on a worker pool; the worker runs it on `sync`; returns a promise |
| deferred | `Loop::defer()`, next turn, here; returns a promise |
| failover | tries `connections` in order |
| null | discards |

# Database driver

Laravel 12's. `pop()` reserves the oldest available row in a transaction, `FOR UPDATE SKIP LOCKED` on MySQL 8.0.1+, MariaDB 10.6+, Postgres 9.5+, Vitess 19+; SQL Server read-past hint; else a plain lock. A row reserved longer than `retry_after` is taken back. `pendingSize`, `delayedSize`, `reservedSize`, `creationTimeOfOldestPendingJob`, `clear`.[^database]

# Background

`pool` config: `thread`, `process`, or unset for thread workers when bound, else process workers. The job must serialize and autoload in the worker.[^background]

# Worker

`queue:work {connection} --once --stop-when-empty --queue --sleep --tries --timeout …` is Laravel's blocking daemon, in its own process. Between jobs it flushes log context, resets query duration handlers, and forgets scoped instances. `JobFailed` is logged to `queue.failer` by the command's listeners, per signal dispatcher. `queue:restart` reads the default cache store.[^work]

# Failed jobs

`queue.failed.driver`: `file` (default; `storage/framework/cache/failed-jobs.json`), `database-uuids`, `null`; a `table` with no driver uses `database`.[^provider][^config]

# Context

A pushed job carries the log Context it was dispatched in (`voyager:log:context` in the payload); the worker hydrates it on `JobProcessing`.[^context]

# Commands

`queue:work`, `queue:listen`, `queue:retry`, `queue:retry-batch`, `queue:failed`, `queue:forget`, `queue:flush`, `queue:clear`, `queue:monitor`, `queue:pause`, `queue:resume`, `queue:restart`, `queue:prune-batches`, `queue:prune-failed`. Dev: `make:queue-table` (`queue:table`), `make:queue-failed-table` (`queue:failed-table`), `make:queue-batches-table` (`queue:batches-table`), each once, on `Console\MigrationGeneratorCommand`.

Async pushes: [async queue](async.md).

[^provider]: QueueServiceProvider
[^config]: queue config
[^database]: DatabaseQueue
[^background]: BackgroundQueue
[^deferred]: DeferredQueue
[^work]: WorkCommand
[^context]: ContextServiceProvider
[^handleable]: Handleable
