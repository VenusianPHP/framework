---
type: Module
title: Concurrency drivers
description: sync, process and fork behind one run()/defer(), and what 0.9 had to grow for them.
resource: src/Voyager/Concurrency/ConcurrencyManager.php
tags: [concurrency, process, defer]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
verified: { by: claude-opus-5, at: 2026-09-22 }
sources:
  - id: manager
    resource: src/Voyager/Concurrency/ConcurrencyManager.php
    title: ConcurrencyManager
  - id: process
    resource: src/Voyager/Concurrency/ProcessDriver.php
    title: ProcessDriver
  - id: command
    resource: src/Voyager/Concurrency/Console/InvokeSerializedClosureCommand.php
    title: invoke-serialized-closure
  - id: console
    resource: src/Voyager/Console/ComputerConsoleInstance.php
    title: ComputerConsoleInstance
  - id: helper
    resource: src/Voyager/NutsAndBolts/Helpers/functions.php
    title: defer() helper
---

# Overview

`ConcurrencyManager` is a `MultipleInstanceManager`. `driver($name)` gives `sync`, `process` or `fork`; each answers `run(Closure|array): array` and `defer(Closure|array): DeferredCallback`. `config/concurrency.php` picks the default, `process`.[^manager]

`process` serializes each closure into `VENUSIAN_INVOKABLE_CLOSURE`, then runs `php computer invoke-serialized-closure` once per task through a `Process\Pool`. The child writes one JSON blob to stdout; the parent maps it back onto the task's key, and rebuilds a child exception from its constructor parameters when it has them.[^process][^command]

`fork` needs `spatie/fork`, which is a suggest of the concurrency package, not a require. The driver has no console guard. `config/concurrency.php` still says fork may only be used from the console; the code does not enforce that. `runningInConsole()` is gone: every Venusian app is console.[^manager]

# What 0.9 had to grow

`Voyager\NutsAndBolts\defer()`. Every driver's `defer()` calls it. `FoundationServiceProvider` already bound `DeferredCallbackCollection` and drained it on command-finished, so only the helper was missing.[^helper]

`ComputerConsoleInstance::phpBinary()`, `computerBinary()` and `formatCommandString()`. 0.8 kept these on `Console\Application`, which is `ComputerConsoleInstance` here. `escapeshellarg()` replaces 0.8's `ProcessUtils::escapeArgument`; on POSIX the two produce the same string.[^console]

`invoke-serialized-closure` on `ComputerServiceProvider`'s command list. Without it every child exits with `CommandNotFound`.[^command]

# Trap

Two closures on one source line serialize to the same closure. `laravel/serializable-closure` locates a closure by file and line, so `run(['a' => fn () => 1, 'b' => fn () => 2])` written on one line runs the first task twice. That is upstream, not ours. Give each task its own line.
