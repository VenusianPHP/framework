---
type: Module
title: Defer
description: $loop->defer() runs a closure on the next turn and settles a promise.
resource: src/Voyager/IOPools/Deferrals.php
tags: [iopools, defer]
status: draft
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: deferrals
    resource: src/Voyager/IOPools/Deferrals.php
    title: Deferrals
  - id: contract
    resource: src/Voyager/Contracts/IOPools/Loop.php
    title: Loop::defer
---

# Overview

`Loop::defer(Closure $work): Promise` queues `$work` and returns a promise. The promise resolves with the return value, or rejects with the throwable.[^contract]

`Deferrals` is a `Tickable` named `deferrals`. It registers on the first item and forgets itself when it takes the batch. Work that calls `defer()` again lands on the next turn, not the same tick. An empty queue does not keep `run()` alive.[^deferrals]

[^deferrals]: Deferrals
[^contract]: Loop::defer
