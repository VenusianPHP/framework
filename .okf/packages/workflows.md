---
type: PHP Package
title: voyager/workflows
description: Graph workflows — prep/exec/post nodes, action-routed flows, and a driver-based async runtime.
resource: ../../src/Voyager/Workflows
tags: [php, package, voyager, workflows, async, graph]
status: draft
generated: { by: agent:cursor, at: 2026-08-22T20:00:00Z }
sources:
  - id: package-source
    resource: ../../src/Voyager/Workflows
    title: Workflows package source
  - id: package-manifest
    resource: ../../src/Voyager/Workflows/composer.json
    title: voyager/workflows composer.json
  - id: runtime-config
    resource: ../../config/workflows.php
    title: config/workflows.php
  - id: upstream
    resource: https://github.com/The-Pocket/PocketFlow-PHP
    title: PocketFlow-PHP, the graph model this package descends from
---

# Overview

A directed graph of nodes, each running a `prep` -> `exec` -> `post` lifecycle,
where the string returned by `post` names the successor to visit. Descended from
PocketFlow-PHP, with the async layer rebuilt.

Sync surface: `BaseNode`, `Node` (retry plus `execFallback`), `Flow`,
`ConditionalTransition`, `SharedBag` (`#[AllowDynamicProperties]`).

Async surface: `AsyncNode`, `AsyncFlow`, `AsyncBatchNode`,
`AsyncParallelBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchFlow`, all built
on the `AsyncWorkflowLogic` trait.

# The async runtime seam

Upstream types every async lifecycle method against `React\Promise\PromiseInterface`
and drives it with `React\Async\async`/`await`. This package instead routes all
asynchronous behaviour through five operations on
`Voyager\Contracts\Workflows\AsyncRuntime`:

* `async(Closure): Awaitable` — begin work
* `resolve(mixed): Awaitable` — lift a plain value
* `await(mixed): mixed` — the only blocking point; passes non-awaitables through
* `all(iterable, ?int $concurrency): Awaitable` — fan out, keys preserved
* `delay(float): Awaitable` — backoff that does not block siblings

`Voyager\Contracts\Workflows\Awaitable` is deliberately minimal: one `then()`.
Awaitables are **not** portable between runtimes; each runtime rejects foreign
ones with `WorkflowRuntimeException`.

Shipped runtimes, resolved by `AsyncRuntimeManager` (extends
`Voyager\NutsAndBolts\Manager`) from `config('workflows.runtime')`, named by the
`AsyncRuntimeDriver` enum:

| Driver | Class | Needs | Overlaps work |
| --- | --- | --- | --- |
| `sync` | `Runtimes\SyncRuntime` | nothing | no |
| `fiber` | `Runtimes\FiberRuntime` | nothing | yes, at await points |
| `react` | `Runtimes\ReactRuntime` | `react/async` (suggest) | yes |

`sync` is the default so async graphs run correctly and deterministically with
no optional package installed. `Manager::extend()` registers further runtimes
without touching the package.

# Behaviour worth knowing

* **Lifecycle methods return `mixed`.** A plain value or an `Awaitable` are both
  accepted; the runtime normalises. This is what lets one node run unchanged on
  every runtime.
* **A graph walk is sequential.** The successor is unknown until `post` returns,
  so `AsyncFlow` gains no overlap from the walk itself. Overlap comes from
  `AsyncParallelBatchNode`, `AsyncParallelBatchFlow`, and fan-out a user writes
  inside one node.
* **Retry backoff uses `AsyncRuntime::delay()`**, never `sleep()`.
* **Group failure policy:** every entry settles, then the first rejection in key
  order is thrown. Siblings are not cancelled.
* **`AsyncFlow::_runAsync` runs `prepAsync` and `postAsync`** around
  orchestration, matching sync `Flow::_run`. Upstream's does not.
* **Batch flows put their loop in `_runAsync`, not `runAsync`**, so nesting one
  inside another `AsyncFlow` still walks the whole param list. Upstream's
  silently runs a single orchestration.
* **`AsyncParallelBatchFlow` sets `isolatesNodes`**, cloning each node it visits,
  because params live on node state and concurrent branches would overwrite each
  other. Every other flow shares node instances, so node state stays observable.
* **`SharedBag` is one object across concurrent branches.** Fine on the shipped
  runtimes, which are all single-process; treat cross-branch writes as a design
  decision, not a guarantee.
* **A sync `Flow` refuses `AsyncRunnable` members**, and async nodes and flows
  refuse `run()`.

# Known gaps

* No sync `BatchNode` / `BatchFlow` yet; only the async batch variants exist.
* `WorkflowsServiceProvider` is not in `DefaultProviders`.
* `AsyncRunnable` still cannot declare `_runAsync`, since its `SharedBag`
  parameter lives in this package rather than in Contracts.

# Related

- [voyager/contracts](contracts.md)
- [voyager/nuts-and-bolts](nuts-and-bolts.md)

[^package-source]: Workflows package source
[^package-manifest]: voyager/workflows composer.json
[^runtime-config]: config/workflows.php
[^upstream]: PocketFlow-PHP, the graph model this package descends from
