---
type: Module
title: Resources and waiters
description: Loop resource kinds by interface, the one-sleeper succession, background resources, and the kqueue/epoll/select waiter backends.
resource: src/Voyager/IOPools/ResourceRegistry.php
tags: [iopools, loop, resources, kqueue, epoll]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: registry
    resource: src/Voyager/IOPools/ResourceRegistry.php
    title: ResourceRegistry
  - id: waiter
    resource: src/Voyager/IOPools/LoopWaiter.php
    title: LoopWaiter
  - id: kinds
    resource: src/Voyager/Contracts/IOPools/LoopResources
    title: Resource kind contracts
  - id: backends
    resource: src/Voyager/IOPools/Waiter/WaiterBackendManager.php
    title: WaiterBackendManager
  - id: relay
    resource: src/Voyager/IOPools/Waiter/SignalRelay.php
    title: SignalRelay
---

# Kinds

`$loop->resource($name, $resource)` files a resource by every kind it implements. None implemented throws. Reusing a name replaces it everywhere.[^registry][^kinds]

| Kind | Contract | Base class | Loop calls |
|---|---|---|---|
| Wakeable | `wakes(): list<Wake>`, `woke(fired)` | `WakeSource` | `wakes()` re-read every turn |
| Deadlined | `dueAt(): ?int` (ns), `fire()` | `Deadline` | fire when due |
| Timer | `Deadlined` + `interval()`, `cancel()` | `Timer` | one-shot forgotten after firing |
| Tickable | `tick()` | `Pollable` | every turn; caps the wait at the pace |
| Sleepable | `Tickable` + `sleep(budget_ns)` | `Sleeper` | first in line holds the turn's sleep |
| Resumable | `resume(): bool`, `pending(): bool` | `FollowOnResource` | pending makes the wait a glance |
| Pumpable | `pump(): list<object>` | — | mail out each turn; last pump on `forget()` |
| Background | marker | — | never keeps `run()` alive |

Sleepers queue in succession. First holds the sleep; the rest count as polled. `forget()` hands the sleep to the next. `crown($name)` moves one to the front.[^registry]

`hasWork()`: any non-background wakeable, pollable or sleeper; any deadline; any pending follow-on.[^registry]

# Waiting

`LoopWaiter::wait()` syncs the backend with every owner's declared wakes, then: a sleeper sleeps inside its own call and the backend gets a zero-timeout glance; otherwise the backend waits until the deadline. Pending follow-ons make the timeout 0. Pollables, extra sleepers, or only background wakes held cap it at `pace_ms`.[^waiter]

Wake reasons: `READABLE`, `WRITEABLE`, `CONTROL_SIGNAL`, `PROCESS_EXIT`, `FILE_CHANGE`. `$loop->supports($kind)` before declaring one; an unsupported wake throws at attach.[^waiter]

# Backends

`io-pools.pool_waiters.default`: `auto` picks epoll (ext-epoll), else kqueue (ext-kqueue), else select.[^backends]

| Backend | Waits on |
|---|---|
| kqueue | every reason; control signals need pcntl |
| epoll | read, write |
| select | read, write |

Where the backend lacks control signals and pcntl is loaded, `SignalRelay` turns signals into a byte on a socket pair the backend reads. Handlers run async (`pcntl_async_signals`).[^relay]

[^registry]: ResourceRegistry
[^waiter]: LoopWaiter
[^kinds]: Resource kind contracts
[^backends]: WaiterBackendManager
[^relay]: SignalRelay
