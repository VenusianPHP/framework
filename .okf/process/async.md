---
type: Module
title: Process on the loop
description: waitAsync() and runAsync() wait on a process through the loop - exit wake, own timeout deadline, output read while wanted.
resource: src/Voyager/Process/ProcessWatch.php
tags: [process, loop, async]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: watch
    resource: src/Voyager/Process/ProcessWatch.php
    title: ProcessWatch
  - id: invoked
    resource: src/Voyager/Process/InvokedProcess.php
    title: InvokedProcess
  - id: pending
    resource: src/Voyager/Process/PendingProcess.php
    title: PendingProcess
  - id: pool
    resource: src/Voyager/Process/InvokedProcessPool.php
    title: InvokedProcessPool
---

# Overview

Laravel's process API on Symfony Process, no provider: build a `Voyager\Process\Factory`. Blocking `run()` / `wait()` as ever.[^pending]

Async: `PendingProcess::runAsync($command, $output)`, `InvokedProcess::waitAsync($output)` → promise of `ProcessResult`, or `ProcessTimedOutException`. `Pool::runAsync()` / `InvokedProcessPool::waitAsync()` → `ProcessPoolResults` keyed as the pool was, or the first failure. `Pipe::runAsync()` too.[^invoked][^pool]

# How it waits

`ProcessWatch` is on the loop until the process exits. Exit wakes the loop: a `ProcessExit` wake on kqueue; elsewhere SIGCHLD through the waiter's signal relay (handled, never ignored, so exit codes survive). The first check runs on the first turn after it registers, so an exit that came earlier is found.[^watch]

Timeout is one deadline: start plus timeout. Output is read every poll interval only while an output callback wants it, an idle timeout needs checking, or no exit wake exists; otherwise the whole output is in the result.[^watch]

[^watch]: ProcessWatch
[^invoked]: InvokedProcess
[^pending]: PendingProcess
[^pool]: InvokedProcessPool
