---
type: Module
title: Workflow graph
description: PocketFlow node graph; AsyncRuntime returns IOPools promises; the loop runtime is the only one, and a node with no app runs on a standalone loop.
resource: src/Voyager/Workflows/Runtimes/LoopRuntime.php
tags: [workflows, nodes, async]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: runtime
    resource: src/Voyager/Workflows/Runtimes/LoopRuntime.php
    title: LoopRuntime
  - id: manager
    resource: src/Voyager/Workflows/AsyncRuntimeManager.php
    title: AsyncRuntimeManager
  - id: resolver
    resource: src/Voyager/Workflows/Concerns/ResolvesAsyncRuntime.php
    title: ResolvesAsyncRuntime
  - id: contract
    resource: src/Voyager/Contracts/Workflows/AsyncRuntime.php
    title: AsyncRuntime
---

# Overview

Node graph under `Voyager\Workflows` and `Voyager\Contracts\Workflows`. Lifecycle prep → exec → post; async methods may return a value or a `Voyager\Contracts\IOPools\Promise`.[^contract]

| Piece | Where |
|---|---|
| Sync graph | `BaseNode`, `Node`, `Flow`, `SharedBag`, `ConditionalTransition` |
| Async graph | `AsyncNode`, `AsyncFlow`, `AsyncBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchNode`, `AsyncParallelBatchFlow`, `AsyncWorkflowLogic` |
| Runtime contract | `async`, `resolve`, `await`, `all`, `delay`; `LoopRuntime` adds `loop()` |

`LoopRuntime` is the one runtime: `async` / `await` / `delay` sit on `Loop::async` / `await` / `at`. `await()` borrows on the main stack and suspends inside a loop fiber; nothing sleeps. `all($work, $concurrency)` caps in-flight work, waits for every entry, then resolves keyed results or rejects with the first failure.[^runtime]

`AsyncRuntimeManager` (`WorkflowsServiceProvider`): driver `loop`, from `workflows.runtime`; `extend()` adds others.[^manager]

A node takes the app's runtime (the manager's driver, else a `LoopRuntime` on the bound loop). With no app it uses `LoopRuntime::standalone()`: one loop per process from IOPools' parts, best waiter backend, Guzzle promise engine.[^resolver][^runtime]

Parallel batches fan items through `all()`; branches get their own copy of each node visited; `SharedBag` stays one object.

Retries: `execAsync` gets `maxRetries` tries with `wait` seconds between them (waited on the loop), then `execFallbackAsync`. A `CancelledException` is never retried; it propagates.[^runtime]

[^runtime]: LoopRuntime
[^manager]: AsyncRuntimeManager
[^resolver]: ResolvesAsyncRuntime
[^contract]: AsyncRuntime
