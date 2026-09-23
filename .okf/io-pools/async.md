---
type: Module
title: Async
description: $loop->async() runs a body in a fiber so wait() suspends instead of borrowing.
resource: src/Voyager/IOPools/FiberScheduler.php
tags: [iopools, fiber, async]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.6, at: 2026-09-22T16:10:00Z }
sources:
  - id: scheduler
    resource: src/Voyager/IOPools/FiberScheduler.php
    title: FiberScheduler
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop::async and until
---

# Overview

`Loop::async(callable): Task` starts a fiber and returns a `Task`. `Task` wraps a normal promise. `then()`, `error()`, and `finally()` return plain promises. `cancel()` throws `CancelledException` into the fiber at its suspend point.[^scheduler]

`Loop::await()` waits on the `Promise` contract, which includes `Task`. Checking the concrete `Voyager\IOPools\Promise` class instead wraps a `Task` as a foreign thenable; `Task::then()` takes one callback, so `adopt()` throws `ArgumentCountError` before `until()` runs.[^loop]

# When wait suspends

`Promise::wait()` calls `until()`. On the main stack, `until()` turns the loop. Inside a fiber the scheduler owns, `until()` calls `Fiber::suspend`. A foreign fiber, or a `FiberError` from a C frame, still borrows the loop.[^loop]

The scheduler resumes a fiber when its wake assertion holds, in the resume phase after the promise engine flushes. If `run()` ends with only suspended fibers left, or a main-stack `until()` has nothing that can wake them, those fibers are cancelled first.[^scheduler]

[^scheduler]: FiberScheduler
[^loop]: EventLoop::async and until
