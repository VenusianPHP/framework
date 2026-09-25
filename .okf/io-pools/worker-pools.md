---
type: Module
title: Worker pools
description: "One submit(): Promise API with a process driver and a ZTS thread driver."
resource: src/Voyager/IOPools/WorkerPoolManager.php
tags: [iopools, pool, process, thread]
status: draft
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: manager
    resource: src/Voyager/IOPools/WorkerPoolManager.php
    title: WorkerPoolManager
  - id: config
    resource: config/io-pools.php
    title: io-pools config
  - id: thread
    resource: src/Voyager/IOPools/ThreadPool.php
    title: ThreadPool
  - id: worker
    resource: src/Voyager/IOPools/bin/pool-worker
    title: pool-worker
  - id: thread-runtime
    resource: src/Voyager/IOPools/ThreadRuntime.php
    title: ThreadRuntime
  - id: frame
    resource: src/Voyager/IOPools/ProcessPoolFrame.php
    title: ProcessPoolFrame
  - id: process-worker
    resource: src/Voyager/IOPools/ProcessPoolWorker.php
    title: ProcessPoolWorker
---

# Overview

`WorkerPool::submit(ShouldPool): Promise` is the same call for both drivers. `process` is the default. `thread` needs `ext-parallel` on a ZTS build and refuses to start while Xdebug's mode is not `off`.[^config][^thread]

`Pool` owns the queue, idle pick, recycle, shutdown, and `warm()`. `ProcessPool` and `ThreadPool` implement `spawn()`. `ThreadPool` also owns the unix socket and the sweep timer (`shutDown`, `afterAssign`, `afterSettle`). `ProcessPool` adds `pids()`.[^thread]

# Crossing the boundary

Both drivers move a gig as a serialized string and settle the promise from one envelope: `{ok, value}` or `{ok: false, class, message, trace}`. A failure becomes `RemoteException`. No exception object crosses.

# What a worker boots

Both entry points run `VenusianVoyager::launch($base_path)` and then the console kernel's `bootstrap()`. `launch()` alone only builds the container, so before that call no gig could resolve a provider's binding — `app('hash')` threw `Target class [hash] does not exist`. `RegisterProviders` writes the package manifest, so a worker's `base_path` needs a writable `bootstrap/cache`. Those dirs belong in the Venusian app skeleton, not this package. `tests/Pest.php` mkdir's `bootstrap/cache` and `storage/app` before any test so a clean Pest run (CI or local) has them on disk when a child boots. A missing cache dir throws from `PackageManifest::write()`. `pool-worker` catches a bootstrap failure and writes the message to stderr — stdout is the frame protocol, and `HandleExceptions` would otherwise render onto it, leaving `DeadWorkerException` with an empty tail.[^worker][^thread-runtime]

A process worker's stdout carries frames only: `VFP\x01`, a 4-byte big-endian length, a serialized array. `pool-worker` opens with `ob_start` to stderr, then says `['hello' => pid]` once booted. The parent holds the first gig's encoded frame until that hello; a booting worker isn't reading stdin, and a frame past the pipe buffer would block the parent in `fwrite()`. No hello within `hello_timeout_s` (default 5.0) rejects the gig with `EventLoopException` ("did not answer") and kills the worker. Bytes outside a frame, or a first frame that isn't a hello, reject with `DeadWorkerException` quoting the bytes. A compile warning in `pool-worker` itself fires before `ob_start` and lands on stdout; that path fails fast instead of reading text as a length.[^frame][^process-worker][^worker]

A thread has no stdout pipe. It writes `!` on a unix socket the loop already selects. The worker's name arrives as `name\n` at accept. `exit()` inside a gig does not ring that socket. A sweep that exists only while a thread is busy notices `Future::done()` and calls `died()`. `died()` replaces the runtime only when the waiting queue is not empty.

# Config

The key is `io-pools.thread_pool` even though it configures both drivers. `size` caps busy workers. `max_jobs` recycles a worker; null never recycles. `sweep_seconds` is thread-only and defaults to `0.5`.[^config]

[^manager]: WorkerPoolManager
[^config]: io-pools config
[^thread]: ThreadPool
[^worker]: pool-worker
[^thread-runtime]: ThreadRuntime
