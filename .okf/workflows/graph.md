---
type: Module
title: Workflow graph
description: PocketFlow-shaped node graph ported from 0.8; awaitables are IOPools promises, and a bare node gets an isolated loop.
resource: src/Voyager/Workflows/BaseNode.php
tags: [workflows, async, promises]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.6, at: 2026-09-22T16:10:00Z }
sources:
  - id: plan
    resource: docs/superpowers/plans/2026-09-22-workflows.md
    title: Workflows implementation plan
    last_modified: 2026-09-22
  - id: contract
    resource: src/Voyager/Contracts/Workflows/AsyncRuntime.php
    title: AsyncRuntime
  - id: resolver
    resource: src/Voyager/Workflows/Concerns/ResolvesAsyncRuntime.php
    title: ResolvesAsyncRuntime
  - id: runtime
    resource: src/Voyager/Workflows/Runtimes/LoopRuntime.php
    title: LoopRuntime
  - id: manager
    resource: src/Voyager/Workflows/AsyncRuntimeManager.php
    title: AsyncRuntimeManager
---

# Overview

The 0.8 node graph lives under `Voyager\Workflows` and `Voyager\Contracts\Workflows`. `FiberRuntime`, `ReactRuntime`, and `SyncRuntime` were not copied. `Awaitable` is gone; `AsyncRuntime` returns `Voyager\Contracts\IOPools\Promise`.[^contract]

A node with no runtime set constructs `LoopRuntime::isolated()`, a private event loop, so a bare `new SomeNode()` needs no container.[^resolver] `LoopRuntime` is the only `AsyncRuntime`: `async`/`await`/`delay` sit on `Loop::async`/`await`/`at`. `all()` gates, waits until every entry has settled, then rejects the promise with the first failure; `await()` is what throws.[^runtime] `AsyncRuntimeManager` offers `loop` (the app's loop) and `isolated` (a private `EventLoop`).[^manager] `WorkflowsServiceProvider` is deferred and listed in `DefaultProviders`. This package is in the root `replace` list. `WorkflowRuntimeException` extends `Voyager\Contracts\Core\VenusianFrameworkException` (not `Contracts\System`). A parallel-batch node fans items through `LoopRuntime::all()`; it does not call `WorkerPool::submit()`.

# Schema

| Piece | Where |
|---|---|
| Sync graph | `BaseNode`, `Node`, `Flow`, `SharedBag`, `ConditionalTransition` |
| Async graph | `AsyncNode`, `AsyncFlow`, `AsyncBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchNode`, `AsyncParallelBatchFlow`, `AsyncWorkflowLogic` |
| Contract | `async`, `resolve`, `await`, `all`, `delay` |
| Runtime | `LoopRuntime` |
| Drivers | `loop`, `isolated` via `AsyncRuntimeManager` |

[^plan]: Workflows implementation plan
[^contract]: AsyncRuntime
[^resolver]: ResolvesAsyncRuntime
[^runtime]: LoopRuntime
[^manager]: AsyncRuntimeManager
