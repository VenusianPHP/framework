---
type: Module
title: Event loop
description: The loop that waits on timers, streams, and tickables, and how until() borrows it.
resource: src/Voyager/IOPools/EventLoop.php
tags: [iopools, loop]
status: draft
generated: { by: claude-opus-5-5, at: '2026-09-23T16:21:52Z' }
sources:
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop
  - id: contract
    resource: src/Voyager/Contracts/IOPools/Loop.php
    title: Loop contract
---

# Overview

`Voyager\IOPools\EventLoop` implements `Voyager\Contracts\IOPools\Loop`. `run()` turns until nothing is due and no resource is registered, or until `stop()`. `until(Closure)` on the main stack turns quietly until the closure returns true; mail stays in the bag during those quiet turns. Out of timers and resources, `until()` flushes the promise engine once more before it throws "ran out of work"; a settled foreign promise's queued callbacks count as work.[^loop] Inside a fiber `async()` started, `until()` suspends; the outer `run()` is not quiet, so mail can hand off while that fiber is parked.[^loop]

`adopt(object $thenable)` wraps a foreign thenable (a Guzzle promise included) as a `Voyager\Contracts\IOPools\Promise`. `wait()` on that promise calls `until()` until the wrap has settled. On the main stack that borrows the loop; inside a scheduler-owned fiber it suspends. The Http drivers rely on this; see [async drivers](/http/async-drivers.md).[^loop]

`then()` takes an optional second callable: a foreign library assimilating a returned loop promise calls `then($resolve, $reject)`, and the reject handler must reach the chain.[^loop]

A `Tickable` registered through `resource()` counts as work. `hasResources()` does not count a `Resumable`. The fiber scheduler is a `Resumable` named `fibers` and stays registered for the life of the loop.[^loop]

# What a turn does

1. Wait: a `Sleepable` owns the sleep when one is registered; otherwise wait on the next timer or on streams; with neither, sleep the fallback tick budget.
2. Read ready streams, fire due timers, tick tickables.
3. Flush the promise engine, then `resume()` resumables, repeating until a pass does nothing.
4. Pump mail. If the turn is not quiet, hand the mail to the mail handler.

[^loop]: EventLoop
