---
type: PHP Package
title: voyager/workflows
description: Graph workflows — prep/exec/post nodes, action-routed flows, and a driver-based async runtime.
resource: ../../src/Voyager/Workflows
tags: [php, package, voyager, workflows, async, graph]
status: draft
generated: { by: agent:cursor-opus-4.8, at: 2026-08-23T05:05:00Z }
stale_after: 2026-11-22
sources:
  - id: package-source
    resource: ../../src/Voyager/Workflows
    title: Workflows package source (23 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Workflows/composer.json
    title: voyager/workflows composer.json
  - id: runtime-config
    resource: ../../config/workflows.php
    title: config/workflows.php
  - id: contracts
    resource: ../../src/Voyager/Contracts/Workflows
    title: Voyager\Contracts\Workflows (6 PHP files)
  - id: tests
    resource: ../../tests/Workflows
    title: Workflows Pest suite (5 files)
  - id: pest-datasets
    resource: ../../tests/Pest.php
    title: async runtimes and overlapping async runtimes datasets
  - id: upstream
    resource: https://github.com/The-Pocket/PocketFlow-PHP
    title: PocketFlow-PHP, the graph model this package descends from
---

# Overview

A directed graph of nodes, each running a `prep` -> `exec` -> `post` lifecycle,
where the string returned by `post` names the successor to visit. Descended from
PocketFlow-PHP, with the async layer rebuilt.

**23** PHP files under `src/Voyager/Workflows/`. Contracts live in
`src/Voyager/Contracts/Workflows/` (**6** PHP files). Package name
`voyager/workflows`. Requires `voyager/contracts` and `voyager/nuts-and-bolts`
`^0.8.0`. Suggests `react/async` for the react runtime. Root
`require-dev` already pins `react/async ^4.0`; that is not a production
require.

Sync surface: `BaseNode`, `Node` (retry plus `execFallback`), `Flow`,
`ConditionalTransition`, `SharedBag` (`#[AllowDynamicProperties]`).

Async surface: `AsyncNode`, `AsyncFlow`, `AsyncBatchNode`,
`AsyncParallelBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchFlow`, all built
on the `AsyncWorkflowLogic` trait.

# Tests

`tests/Workflows/` is **5** Pest v4 files (closures, no `TestCase`):

* `AsyncRuntimeTest.php`
* `AsyncRuntimeManagerTest.php`
* `AsyncNodeTest.php`
* `AsyncFlowTest.php`
* `AsyncBatchTest.php`

`tests/Pest.php` defines two datasets. `async runtimes` is sync, fiber, and
react when `React\Async\async` exists. `overlapping async runtimes` is fiber
and the same optional react row. Sync is the CI default; fiber needs no extra
package; react is filtered out if `react/async` is missing.

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

`AsyncRuntimeManager::driver()` stays untyped on the parameter. Parent
`Manager::driver($driver = null)` is untyped; narrowing the child to
`UnitEnum|string|null` fatals (LSP). The return type is `AsyncRuntime`.
The body already accepts a backed enum, a unit enum, or a string.

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
* **`FiberRuntime::loop()` re-checks settlement after `expireTimers()`.** A
  top-level `await(delay())` fulfills the last timer and empties the schedule
  in the same turn; treating that as deadlock was a bug the suite caught on
  first execution. PHP 8.5 deprecated `SplObjectStorage::attach` /
  `contains` / `detach`; this runtime uses array access.

# Generators

`computer make:node <Name>` scaffolds a Workflow node into `App\Workflows`
(`app/Workflows/`), extending `Voyager\Workflows\Node` with `prep` / `exec` /
`post(SharedBag): ?string`. `make:node --async` extends
`Voyager\Workflows\AsyncNode` with `prepAsync` / `execAsync` / `postAsync`
instead; when run interactively without options it prompts for the async choice.
The command is `Voyager\System\Console\NodeMakeCommand` (stubs `node.stub` /
`node.async.stub`), registered as a **dev** command in `ComputerServiceProvider`
— so it does **not** depend on `WorkflowsServiceProvider` being in
`DefaultProviders`. Ported from 0.7.x `Fabricate\Core\Console\NodeMakeCommand`,
whose stubs targeted the removed `Fabricate\Sketches\Flow` classes.

# Known gaps

* No sync `BatchNode` / `BatchFlow` yet; only the async batch variants exist.
* `WorkflowsServiceProvider` is not in `DefaultProviders` (the `make:node`
  generator does not need it; it lives on the always-registered Computer CLI).
* `AsyncRunnable` still cannot declare `_runAsync`, since its `SharedBag`
  parameter lives in this package rather than in Contracts.

Sketches (0.8) no longer embeds a Flow copy. 0.7 ran sketches through a
2-node PocketFlow (`BootSketchNode` → self-looping `TickSketchNode`) plus
`Fabricate\Sketches\Flow\*`. That tree is gone. `voyager/sketches` does not
require this package. A sketch *may* still call a workflow from `loop()`.
See [voyager/sketches](sketches.md).

# Related

- [voyager/contracts](contracts.md)
- [voyager/nuts-and-bolts](nuts-and-bolts.md)
- [voyager/sketches](sketches.md)

[^package-source]: Workflows package source (23 PHP files)
[^package-manifest]: voyager/workflows composer.json
[^runtime-config]: config/workflows.php
[^contracts]: Voyager\Contracts\Workflows (6 PHP files)
[^tests]: Workflows Pest suite (5 files)
[^pest-datasets]: async runtimes and overlapping async runtimes datasets
[^upstream]: PocketFlow-PHP, the graph model this package descends from
