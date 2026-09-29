---
type: Module
title: Concurrency drivers
description: process, fork, sync and pool drivers behind run(); process and pool also run tasks without blocking through async().
resource: src/Voyager/Concurrency/ConcurrencyManager.php
tags: [concurrency, process, fork, pool]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/Concurrency/ConcurrencyManager.php
    title: ConcurrencyManager
  - id: process
    resource: src/Voyager/Concurrency/ProcessDriver.php
    title: ProcessDriver
  - id: pool
    resource: src/Voyager/Concurrency/PoolDriver.php
    title: PoolDriver
  - id: task
    resource: src/Voyager/Concurrency/RunTask.php
    title: RunTask
  - id: results
    resource: src/Voyager/Concurrency/ConcurrencyResults.php
    title: ConcurrencyResults
  - id: config
    resource: config/concurrency.php
    title: concurrency config
---

# Overview

`app('concurrency')` is `ConcurrencyManager`. `run(Closure|array $tasks): array` blocks and returns results keyed as the tasks were; the first failure in key order is thrown as its own class. Default driver `process` (`CONCURRENCY_DRIVER`).[^manager][^config]

| Driver | Runs each task |
|---|---|
| process | in a fresh `php computer invoke-serialized-closure`; closure in through `VENUSIAN_INVOKABLE_CLOSURE`, result out on stdout |
| fork | through `spatie/fork`; throws without the package |
| sync | in this process |
| pool | as a `RunTask` gig on IOPools' workers; `pool` config `auto`, `thread` or `process` |

Pool workers boot once and are reused, so a task pays no app boot. `RunTask` rebuilds the closure inside `handle()`: a closure the worker can't rebuild fails as that task, not the worker. Parameters resolve through the worker's container, as `invoke-serialized-closure` resolves them.[^pool][^task]

# async()

`async(Closure|array $tasks, string $name = 'default'): Promise` on the manager uses the default driver; only `process` and `pool` implement `AsyncDriver`, others throw `InvalidArgumentException`. The process driver waits on its processes through the loop ([process on the loop](/process/async.md)); a failed or timed-out process is that task's failure. Once every task settles, a `ConcurrencyResults` is posted as loop mail named `concurrency:{name}`, and the promise settles with the results or the first failure in key order.[^manager][^process][^results]

`laravel/serializable-closure` finds a closure by file and line: two closures on one line serialize as the same closure. One task per line.

[^manager]: ConcurrencyManager
[^process]: ProcessDriver
[^pool]: PoolDriver
[^task]: RunTask
[^results]: ConcurrencyResults
[^config]: concurrency config
