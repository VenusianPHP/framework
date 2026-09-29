---
type: Module
title: Sketch runtime
description: Sketch base with boot, loop($mail), shutdown; a re-armed one-shot timer calls loop every frame with the mail since the last; registry and provider.
resource: src/Voyager/Sketches/SketchRunner.php
tags: [sketches, loop, runner]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
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
  - id: config
    resource: config/sketches.php
    title: sketches config
---

# Overview

A sketch is `boot()`, then `loop(array $mail = []): SketchLoopResult` every frame until `STOP`, then `shutdown()` once. Base `Voyager\Sketches\Sketch` uses `InteractsWithIO`, keeps a description, exposes `refreshRate(): ?float` (null leaves config alone).[^sketch]

# Runner

`SketchRunner::run($sketch)`: writes a non-null `refreshRate()` to `sketches.refresh_rate`, boots, arms `$loop->at(1 / hz, frame)`, returns `$loop->run()`. Each frame calls `loop()` with the mail delivered since the last frame (`[]` when none), then re-arms from the key read at that moment, so a sketch can retune itself. Hz below `1.0` clamps to 1. `STOP` stops the loop with status 0; `stop($status)` from outside ends it with that status.[^runner]

Mail reaches the sketch only when the loop was built with the `sketch` mail handler; a rocket run sets it ([loop mail](/io-pools/mail.md)). Otherwise `loop()` gets `[]`.[^runner][^provider]

Shutdown once: `onStop()` hook plus a `finally` around boot and run, guarded per sketch. A throw from `boot()` never frames and still shuts down; a throw from `loop()` is rethrown from `run()`.[^runner]

# Registry

`SketchRegistry` names a class from `#[Voyager\Contracts\Sketches\Attributes\Sketch('name')]`, else `Str::kebab(class_basename)`; a duplicate name throws. `resolve()` builds through the container. `DiscoverSketches::within($paths, $root_namespace, $root_path)` maps files to classes and returns concrete sketches; a missing path returns nothing.[^registry][^discovery]

# Wiring

`SketchesServiceProvider` is deferred. `sketches.registry` (also the class and contract) and `sketches.runner` (loop, config, and the loop's mail handler when it is the sketch one). Config `config/sketches.php`: `refresh_rate` (`SKETCH_REFRESH_RATE`, 60), `load`.[^provider][^config]

Rocket kernel and `php rocket`: [sketch runner](runner.md).

[^runner]: SketchRunner
[^sketch]: Sketch base
[^registry]: SketchRegistry
[^discovery]: DiscoverSketches
[^provider]: SketchesServiceProvider
[^config]: sketches config
