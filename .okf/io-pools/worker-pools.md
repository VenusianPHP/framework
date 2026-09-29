---
type: Module
title: Worker pools
description: submit(ShouldPool) on a named pool, process or thread; no default pool; gigs cross serialized and come back as an envelope.
resource: src/Voyager/IOPools/WorkerPools/WorkerPoolManager.php
tags: [iopools, pool, process, thread, gig]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/IOPools/WorkerPools/WorkerPoolManager.php
    title: WorkerPoolManager
  - id: pool
    resource: src/Voyager/IOPools/WorkerPools/IOPool.php
    title: IOPool
  - id: envelope
    resource: src/Voyager/IOPools/WorkerPools/GigEnvelope.php
    title: GigEnvelope
  - id: process
    resource: src/Voyager/IOPools/WorkerPools/Process/ProcessWorker.php
    title: ProcessWorker
  - id: frame
    resource: src/Voyager/IOPools/WorkerPools/Process/Frame.php
    title: Frame
  - id: thread
    resource: src/Voyager/IOPools/WorkerPools/Thread/ThreadWorker.php
    title: ThreadWorker
  - id: bin
    resource: src/Voyager/IOPools/bin/pool-worker
    title: pool-worker
  - id: config
    resource: config/io-pools.php
    title: io-pools config
---

# Overview

`WorkerPool`: `submit(ShouldPool): Promise`, `warm(int)`, `workerCount()`, `shutDown()`. A gig is `ShouldPool::handle(): mixed`; it crosses serialized, so it carries data, never resources or closures.[^pool]

No default pool. `io-pools.pool_workers.process.enabled` (true) binds `process-workers`; `pool_workers.threads.enabled` (false) binds `thread-workers` and throws at boot unless ZTS with ext-parallel. `max_workers` 4 each. The manager's default driver throws: callers name the pool.[^manager][^config]

Components that offload pick `thread-workers` when bound, else `process-workers`, else throw `InvalidArgumentException` naming the config keys.

# Pool

`IOPool` queues gigs oldest first, reuses an idle worker, spawns while under `max_workers`. A worker is on the loop only while it holds a gig, so idle workers never keep `run()` alive. A dead worker is replaced when gigs wait. `shutDown()` on the loop's `onStop()`: rejects queued gigs, stops workers.[^pool]

# Envelope

Worker runs `GigEnvelope::run()`: `{ok: true, value}` or `{ok: false, class, message, trace}`. Value serialized in the worker first, so an unserializable result fails there. Failure rejects with `RemoteException`; no exception object crosses. A non-`ShouldPool` arrival fails with "Is its class autoloadable in the worker?".[^envelope]

# Process driver

Child runs `bin/pool-worker` with the parent's Composer autoloader and base path. Boots `bootstrap/app.php` when present, else `VenusianVoyager::setup($base_path)->create()`, then the console kernel's `bootstrap()`. Boot failure goes to stderr, exit 1.[^bin]

Stdout carries frames only: magic `VPF\x01`, 4-byte big-endian length, serialized array. Output buffering sends stray prints to stderr. Worker says `hello` after boot; the pool holds the first gig until then, with no timeout. Bytes outside a frame, a result nobody asked for, an exit, or a closed stdin reject the held gig with `DeadWorkerException` quoting the last 2048 bytes of stderr. A gig that won't serialize rejects with `IOPoolsException` and leaves the worker free. Exit wakes the loop via a `ProcessExit` wake where the backend has one.[^frame][^process][^bin]

`RegisterProviders` writes the package manifest, so a worker's base path needs a writable `bootstrap/cache`. `tests/Pest.php` creates `bootstrap/cache` and `storage/app`.

# Thread driver

One ext-parallel runtime per worker. A `Future` has no descriptor, so the thread rings a unix socket: `ready\n` or `failed: …\n` once booted, `!` per finished gig, end-of-file when it dies.[^thread]

[^manager]: WorkerPoolManager
[^pool]: IOPool
[^envelope]: GigEnvelope
[^process]: ProcessWorker
[^frame]: Frame
[^thread]: ThreadWorker
[^bin]: pool-worker
[^config]: io-pools config
