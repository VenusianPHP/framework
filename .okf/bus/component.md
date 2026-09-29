---
type: Module
title: Bus
description: Command bus behind dispatch(); chains; batches kept in the database; unique jobs; BatchFake for jobs under test.
resource: src/Voyager/Bus/Dispatcher.php
tags: [bus, jobs, batches, chains]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: dispatcher
    resource: src/Voyager/Bus/Dispatcher.php
    title: Dispatcher
  - id: provider
    resource: src/Voyager/Bus/BusServiceProvider.php
    title: BusServiceProvider
  - id: batches
    resource: src/Voyager/Bus/DatabaseBatchRepository.php
    title: DatabaseBatchRepository
  - id: pending
    resource: src/Voyager/Core/Bus/PendingDispatch.php
    title: PendingDispatch
  - id: unique
    resource: src/Voyager/Bus/UniqueLock.php
    title: UniqueLock
  - id: fake
    resource: src/Voyager/Bus/Testing/BatchFake.php
    title: BatchFake
---

# Overview

`Dispatcher` binds as itself, `Contracts\Bus\Dispatcher` and `QueueingDispatcher`. `dispatch($job)` returns `Core\Bus\PendingDispatch` (a closure: `PendingClosureDispatch` over `CallQueuedClosure`); the push happens in its destructor. `ShouldQueue` jobs go to their connection, others run now.[^dispatcher][^pending]

`Job::withChain([...])->dispatch(...)` runs the chain in order; each link dispatches the next.[^dispatcher]

# Batches

`$bus->batch([...])->name()->then()->catch()->finally()->onConnection()->dispatch()` → `Batch`. Kept by `DatabaseBatchRepository` in `queue.batching.table` (`job_batches`) on `queue.batching.database`. `queue.batching.driver` other than `database` throws. `Batchable` gives a job `batch()`; `withFakeBatch()` hands it a `Bus\Testing\BatchFake` that records `add()` and `cancel()` and touches no repository.[^provider][^batches][^fake]

# Unique jobs

A `ShouldBeUnique` job takes a cache lock (`UniqueLock`, key from `uniqueId`, `uniqueFor`, `uniqueVia`) in `PendingDispatch`; held, nothing is pushed. Released when the job runs (`ShouldBeUniqueUntilProcessing`) or finishes. Unique job info also goes to the log Context as hidden values.[^unique][^pending]

[^dispatcher]: Dispatcher
[^provider]: BusServiceProvider
[^batches]: DatabaseBatchRepository
[^pending]: PendingDispatch
[^unique]: UniqueLock
[^fake]: BatchFake
