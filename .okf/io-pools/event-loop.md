---
type: Module
title: Event loop
description: The loop that waits on timers, streams, and tickables, and how until() borrows it.
resource: src/Voyager/IOPools/EventLoop.php
tags: [iopools, loop]
status: draft
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop
  - id: contract
    resource: src/Voyager/Contracts/IOPools/Loop.php
    title: Loop contract
---

# Overview

`Voyager\IOPools\EventLoop` implements `Voyager\Contracts\IOPools\Loop`. `run()` turns until nothing is due and no resource is registered. `until(Closure)` turns quietly until the closure returns true. Mail stays in the bag during `until()`.[^loop]

`adopt(object $thenable)` wraps a foreign thenable (a Guzzle promise included) as a `Voyager\Contracts\IOPools\Promise`. `wait()` on that promise borrows the loop with `until()` until the wrap has settled. The Http drivers rely on this; see [async drivers](/http/async-drivers.md).[^loop]

A `Tickable` registered through `resource()` counts as work. `hasResources()` does not count a `Resumable`. The fiber scheduler is a `Resumable` named `fibers` and stays registered for the life of the loop.[^loop]

# What a turn does

1. Wait on the next timer or on streams.
2. Read ready streams, fire due timers, tick tickables.
3. Flush the promise engine, then `resume()` resumables, repeating until a pass does nothing.
4. Pump mail. If the turn is not quiet, hand the mail to the mail handler.

[^loop]: EventLoop
