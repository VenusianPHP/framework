---
type: Module
title: Defer
description: $loop->defer() runs a closure on the next turn and settles a promise with its result.
resource: src/Voyager/IOPools/Deferrals/Deferrals.php
tags: [iopools, defer]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: deferrals
    resource: src/Voyager/IOPools/Deferrals/Deferrals.php
    title: Deferrals
  - id: contract
    resource: src/Voyager/Contracts/IOPools/Loop.php
    title: Loop::defer
---

# Overview

`Loop::defer(Closure $work): Promise` queues the work. Promise resolves with its return value or rejects with its throwable.[^contract]

`Deferrals` is a follow-on resource named `io-pools.deferrals`, registered for the loop's life. Turn start releases what was queued into this turn's batch; the follow-on phase runs the batch. Work that defers again lands on the next turn. Queued work is pending, so the turn's wait is a glance and `run()` stays alive until it runs.[^deferrals]

Users: queue `deferred` driver.

[^deferrals]: Deferrals
[^contract]: Loop::defer
