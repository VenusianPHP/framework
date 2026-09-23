---
type: Module
title: Worker pools
description: "One submit(): Promise API with a process driver and a ZTS thread driver."
resource: src/Voyager/IOPools/WorkerPoolManager.php
tags: [iopools, pool, process, thread]
status: draft
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
---

# Overview

`WorkerPool::submit(ShouldPool): Promise` is the same call for both drivers. `process` is the default. `thread` needs `ext-parallel` on a ZTS build and refuses to start while Xdebug's mode is not `off`.[^config][^thread]

`Pool` owns the queue, idle pick, recycle, shutdown, and `warm()`. `ProcessPool` and `ThreadPool` only differ in `spawn()`.[^thread]

# Crossing the boundary

Both drivers move a gig as a serialized string and settle the promise from one envelope: `{ok, value}` or `{ok: false, class, message, trace}`. A failure becomes `RemoteException`. No exception object crosses.

# What a worker boots

Both entry points run `VenusianVoyager::launch($base_path)` and then the console kernel's `bootstrap()`. `launch()` alone only builds the container, so before that call no gig could resolve a provider's binding — `app('hash')` threw `Target class [hash] does not exist`. `RegisterProviders` writes the package manifest, so a worker's `base_path` needs a writable `bootstrap/cache`.[^worker][^thread-runtime]

A thread has no stdout pipe. It writes `!` on a unix socket the loop already selects. The worker's name arrives as `name\n` at accept. `exit()` inside a gig does not ring that socket. A sweep that exists only while a thread is busy notices `Future::done()` and replaces the runtime.

# Config

The key is `io-pools.thread_pool` even though it configures both drivers. `size` caps busy workers. `max_jobs` recycles a worker; null never recycles. `sweep_seconds` is thread-only and defaults to `0.5`.[^config]

[^manager]: WorkerPoolManager
[^config]: io-pools config
[^thread]: ThreadPool
[^worker]: pool-worker
[^thread-runtime]: ThreadRuntime
