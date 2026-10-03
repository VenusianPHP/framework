---
type: Module
title: Event loop
description: One turn waits once, then fires wakes, deadlines, ticks, follow-ons and mail; until() borrows the loop or suspends a fiber.
resource: src/Voyager/IOPools/EventLoop.php
tags: [iopools, loop]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop
  - id: contract
    resource: src/Voyager/Contracts/IOPools/Loop.php
    title: Loop contract
  - id: provider
    resource: src/Voyager/IOPools/IOPoolsServiceProvider.php
    title: IOPoolsServiceProvider
  - id: config
    resource: config/io-pools.php
    title: io-pools config
  - id: stop
    resource: src/Voyager/IOPools/StopSignals.php
    title: StopSignals
---

# Overview

`Voyager\IOPools\EventLoop` implements `Voyager\Contracts\IOPools\Loop`. Container key `event-loop`; aliases `EventLoop`, `Loop`. Built in `IOPoolsServiceProvider::boot()` from the `loop-resource-registry` singleton, a `LoopWaiter` over the waiter backend, the promise engine, and the mail handler.[^provider]

Contract: `at()`, `every()`, `promise()`, `await()`, `async()`, `defer()`, `post()`, `resource()`, `forget()`, `crown()`, `supports()`, `onStop()`, `run()`, `stop()`, `until()`.[^contract]

# A turn

1. `LoopWaiter::wait()` once, bounded by the soonest deadline. See [resources and waiters](resources.md).
2. Deferrals queued before the turn become this turn's batch.
3. Fired wakes go to their owners (`woke()`), due deadlines fire, tickables tick.
4. Promise engine flushes; resumables (`Deferrals`, `FiberScheduler`) resume; repeat until none ran.
5. Pumpables pump. A non-quiet turn hands collected mail to the [mail handler](mail.md).
6. First throwable any resource raised this turn is rethrown.[^loop]

# run(), stop(), until()

`run()` turns while `stop()` was not called and the registry has foreground work; out of work, it flushes promises first and stops only if that made none. Finally: cancel parked fibers, flush promises, run `onStop()` hooks, flush again. Returns the status `stop()` set.[^loop]

`until(Closure)`: inside a fiber the scheduler owns, suspends on the closure. Otherwise borrows the loop with quiet turns (no mail hand-off). No work left: flush promises, recheck; turn again if the flush made work (a parked fiber whose promise it settled) and the scheduler is not mid-resume; else cancel parked fibers if any, else throw `IOPoolsException` "ran out of work". `stop()` during a borrow throws `IOPoolsException`. A `FiberError` from a C frame falls back to borrowing.[^loop]

`await()` takes a loop promise, any object with `then()` (adopted through the engine), or a plain value.[^loop]

# Stop signals

`StopSignals` is a `Background` wake source on SIGINT/SIGTERM where the backend supports control signals. First signal: `stop(130)` / `stop(143)`, handlers reset to `SIG_DFL`, so a second one kills. `run()` and a standalone `until()` re-arm it.[^stop]

# Config

`io-pools.event_loop.pace_ms` (16) caps a wait while anything polled is registered. `event_loop.mail_handlers.default` (`signal`). `pool_waiters.default` (`auto`). `promise_engines.default` (`guzzle`; `react` needs `react/promise`).[^config]

[^loop]: EventLoop
[^contract]: Loop contract
[^provider]: IOPoolsServiceProvider
[^config]: io-pools config
[^stop]: StopSignals
