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
  - id: provider
    resource: src/Voyager/IOPools/IOPoolsServiceProvider.php
    title: IOPoolsServiceProvider
---

# Overview

`Loop::async(callable): Task` starts a fiber now; it runs to its first suspend or its end before `async()` returns. `Task` is a promise: `then()`, `error()`, `finally()`, `wait()`, plus `cancel()`, which throws `CancelledException` into the fiber at its suspend point.[^task][^scheduler]

`FiberScheduler` is a follow-on resource (`io-pools.fibers`, registered for the loop's life). A parked fiber is pending once its wake condition holds; it resumes in the follow-on phase after promises flush.[^scheduler]

# When wait suspends

`Promise::wait()` is `until(settled)`. Inside a scheduler-owned fiber `until()` suspends; on the main stack it borrows the loop; a `FiberError` from a C frame borrows too.[^loop]

`run()` ending, or a borrow with nothing that can wake the parked fibers, cancels them first.[^loop]

# Fiber-start hooks

`EventLoop::onFiberStart(callable)` adds a hook `FiberScheduler::start()` calls with each new fiber before it runs. The scheduler knows nothing else of it. The app's loop (`IOPoolsServiceProvider`) registers `TheServiceContainer::shareScopeWith`, so a task resolves the scoped instances of the fiber, or main context, that started it ([Scoped bindings](/vessel/scoped.md)). A standalone loop has no hook.[^scheduler][^loop][^provider]

`EventLoop::onFiberSuspend($suspending, $resumed)` registers a pair that runs inside the waiting fiber: `$suspending` just before it suspends on a loop wait (`until()` / `await()` / `wait()`), `$resumed` just after it returns there, by resumption, a throw or a cancel. Suspending hooks run in registration order, resumed ones in reverse, only for the pairs whose suspending hook ran. A suspending hook that throws stops the suspend: the pairs before it are resumed and its exception propagates from the wait. An exception from the suspend point (a cancel, a throw) propagates unchanged, with any resumed hook's exception chained under it as `getPrevious()`; with none in flight, the first resumed hook's exception propagates after every other resumed hook ran.[^scheduler][^loop]

Only fibers the loop started (`async()`) get these hooks: a raw `Fiber::suspend()` in user code, or a fiber the loop did not start, never runs them, and PHP offers no way to intercept those. Work that needs per-fiber state kept across waits, a server's per-connection fibers among it, must run in fibers the loop starts.[^loop]

[^scheduler]: FiberScheduler
[^task]: Task
[^loop]: EventLoop::async and until
[^provider]: IOPoolsServiceProvider
