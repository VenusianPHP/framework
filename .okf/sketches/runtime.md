---
type: Module
title: Sketch runtime
description: Sketch base, a runner that re-arms a one-shot timer on the event loop, and a class-from-path registry.
resource: src/Voyager/Sketches/SketchRunner.php
tags: [sketches, event-loop, registry]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T21:30:00Z }
sources:
  - id: runner
    resource: src/Voyager/Sketches/SketchRunner.php
    title: SketchRunner
  - id: sketch
    resource: src/Voyager/Sketches/Sketch.php
    title: Sketch base
  - id: registry
    resource: src/Voyager/Sketches/SketchRegistry.php
    title: SketchRegistry
  - id: discovery
    resource: src/Voyager/Sketches/DiscoverSketches.php
    title: DiscoverSketches
  - id: provider
    resource: src/Voyager/Sketches/SketchesServiceProvider.php
    title: SketchesServiceProvider
  - id: providers
    resource: src/Voyager/Core/DefaultProviders.php
    title: Default provider list
  - id: loop
    resource: /io-pools/event-loop.md
    title: Event loop
---

# Overview

A sketch is `boot()`, then `loop()` until it returns `SketchLoopResult::STOP`, then `shutdown()` once. The abstract base is `Voyager\Sketches\Sketch`. It uses `InteractsWithIO`, keeps a description, and exposes `refreshRate()` (`null` means leave config alone).[^sketch]

`SketchRunner` takes a `Loop` and the config `Repository`. After `boot()` it arms `$loop->at(1 / hz, tick)`. Each `CONTINUE` arms the next tick from `config('sketches.refresh_rate')` read at that moment, so a sketch can retune itself inside `loop()`. A non-null `refreshRate()` is written to that key before the first tick. `STOP` calls `$loop->stop(0)` and does not re-arm. `stop($status)` from outside ends the run with that status. Hz of `0` or below clamps to `1.0`, so the period is never a division by zero. Three ticks at that floor take about three seconds.[^runner]

Shutdown runs once. The runner registers `$loop->onStop()` and also uses a `finally` around `boot()` / `run()`, guarded by one flag. A throw from `boot()` never ticks and still shuts down. A throw from `loop()` is stored by the loop and rethrown from `run()`; shutdown still happens once. `onStop` hooks are not removed, which is fine because one runner is one process and one sketch.[^runner][^loop]

# Registry

`SketchRegistry` names a class from `#[Voyager\Contracts\Sketches\Attributes\Sketch('name')]`, otherwise `Str::kebab(class_basename)`. A second registration of the same name throws. `resolve()` builds the class through the container. `DiscoverSketches::within($paths, $root_namespace, $root_path)` maps files onto that namespace the way Laravel's kernel `load()` does, and returns concrete subclasses of the sketch contract. A missing path returns nothing.[^registry][^discovery]

# Wiring

`SketchesServiceProvider` is deferred and on `DefaultProviders`. It merges `src/Voyager/Sketches/config/sketches.php` under `sketches` (`refresh_rate` defaults to `60`, `load` is an extra class list). The same file is at `config/sketches.php` and `config-stubs/sketches.php`. Bindings: `app('sketches.registry')` (also the class and the contract) and `app('sketches.runner')` (the loop plus config).[^provider][^providers]

The Rocket kernel and `php rocket` are [Sketch runner](/sketches/runner.md).

[^runner]: SketchRunner
[^sketch]: Sketch base
[^registry]: SketchRegistry
[^discovery]: DiscoverSketches
[^provider]: SketchesServiceProvider
[^providers]: Default provider list
[^loop]: Event loop
