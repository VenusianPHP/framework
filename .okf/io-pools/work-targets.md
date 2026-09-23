---
type: Module
title: Work targets
description: "One run(ShouldPool): Promise contract with five homes — sync, defer, pool, concurrency, queue."
resource: src/Voyager/IOPools/WorkTargetManager.php
tags: [iopools, work-target, filesystem]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T22:10:00Z }
sources:
  - id: contract
    resource: src/Voyager/Contracts/IOPools/WorkTarget.php
    title: WorkTarget
  - id: manager
    resource: src/Voyager/IOPools/WorkTargetManager.php
    title: WorkTargetManager
  - id: via
    resource: src/Voyager/Filesystem/FilesystemAdapter.php
    title: FilesystemAdapter::via
  - id: gig
    resource: src/Voyager/Filesystem/DiskGig.php
    title: DiskGig
---

# Overview

`WorkTarget::run(ShouldPool $gig): Promise` is one call for five homes. `app('work-targets')` is a `WorkTargetManager`. Config key `io-pools.work.default` picks the home; missing that, `pool`.[^contract][^manager]

| Home | What happens | Promise value |
|---|---|---|
| `sync` | `handle()` on the spot | the return |
| `defer` | next loop turn, main thread | the return |
| `pool` | `WorkerPool::submit()` | the return |
| `concurrency` | `ConcurrencyManager->driver()->run()` — blocks | the return |
| `queue` | `QueueFactory::connection()->push($gig)` | the job id |

`concurrency` is isolation, not overlap. `queue` is fire-and-forget; nothing carries `handle()`'s return back.[^manager]

`ConcurrencyServiceProvider` binds `ConcurrencyManager`, not `ConcurrencyDriver`. `createConcurrencyDriver()` uses a bound `Driver` when a test registered one, otherwise `ConcurrencyManager->driver()`.

# via()

`$disk->via($target)->put(...)` returns a promise. The disk itself stays blocking. `via()` never mutates the disk. An on-demand `build()` disk has no name, so `via()` throws `LogicException`. Streams cannot cross a worker.[^via]

Query builders and connections offload the same way: see [database on the loop](../database/loop.md).

`DiskGig` carries disk name + method + args. The worker does `app('filesystem')->disk($name)`. `FileGig` does the same for `app('files')`.[^gig]

`storage()->via($target, $disk)` is the same proxy. `storage()->stream($path)` is a [`FileStreamResource`](../filesystem/file-stream.md): size once, then `readRange` gigs, each result a `FileChunk`. Last chunk and `done()` settle the same turn; `await()` is quiet, so listen for `last === true` when the bytes matter.

[^contract]: WorkTarget
[^manager]: WorkTargetManager
[^via]: FilesystemAdapter::via
[^gig]: DiskGig
