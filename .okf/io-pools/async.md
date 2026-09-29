---
type: Module
title: Async
description: $loop->async() runs a body in a fiber; wait() inside it suspends instead of borrowing the loop.
resource: src/Voyager/IOPools/Deferrals/FiberScheduler.php
tags: [iopools, fiber, async]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: scheduler
    resource: src/Voyager/IOPools/Deferrals/FiberScheduler.php
    title: FiberScheduler
  - id: task
    resource: src/Voyager/IOPools/Deferrals/Task.php
    title: Task
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop::async and until
---

# Overview

`Loop::async(callable): Task` starts a fiber now; it runs to its first suspend or its end before `async()` returns. `Task` is a promise: `then()`, `error()`, `finally()`, `wait()`, plus `cancel()`, which throws `CancelledException` into the fiber at its suspend point.[^task][^scheduler]

`FiberScheduler` is a follow-on resource (`io-pools.fibers`, registered for the loop's life). A parked fiber is pending once its wake condition holds; it resumes in the follow-on phase after promises flush.[^scheduler]

# When wait suspends

`Promise::wait()` is `until(settled)`. Inside a scheduler-owned fiber `until()` suspends; on the main stack it borrows the loop; a `FiberError` from a C frame borrows too.[^loop]

`run()` ending, or a borrow with nothing that can wake the parked fibers, cancels them first.[^loop]

[^scheduler]: FiberScheduler
[^task]: Task
[^loop]: EventLoop::async and until
