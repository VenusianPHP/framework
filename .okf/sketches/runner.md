---
type: Module
title: Sketch runner
description: Rocket kernel runs a sketch on a re-armed one-shot timer. php rocket hands off to it. php computer does not.
resource: src/Voyager/Core/Sketches/Kernel.php
tags: [sketches, rocket, event-loop, kernel]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.7/cursor, at: 2026-09-22T21:45:00Z }
sources:
  - id: runner
    resource: src/Voyager/Sketches/SketchRunner.php
    title: SketchRunner
  - id: kernel
    resource: src/Voyager/Core/Sketches/Kernel.php
    title: Sketches kernel
  - id: config
    resource: config/sketches.php
    title: sketches config
  - id: tests
    resource: tests/Sketches/SketchKernelTest.php
    title: Sketch kernel tests
  - id: command
    resource: src/Voyager/Sketches/Console/RunSketchCommand.php
    title: RunSketchCommand
  - id: instance
    resource: src/Voyager/Core/RenderedInstance.php
    title: RenderedInstance::handleSketch
---

# Sketch

Three verbs: `boot()`, `loop()`, `shutdown()`. `refreshRate(): ?float`. Name is `#[Sketch('name')]`, else `Str::kebab(class_basename)`.[^runner]

# Runner

One-shot timer. After each tick the runner calls `$loop->at(1 / hz, tick)`. Hz is `config('sketches.refresh_rate')` read at that moment. A non-null `refreshRate()` is written to that key before the first tick, for the session. Null leaves config alone. `loop()` can retune the next tick. `0` or below clamps to `1` Hz. `STOP` does not re-arm and calls `$loop->stop(0)`. SIGINT / SIGTERM stay the loop's: 130 / 143. Shutdown runs once, via `$loop->onStop()` plus a `finally`, one flag. A throw inside a tick leaves `run()`, the kernel renders it, exit 1. `boot()` throwing never ticks and still shuts down once.[^runner][^kernel]

# Kernel

`Voyager\Core\Sketches\Kernel`. Same bootstrappers as the console kernel. Rocket is `ComputerConsoleInstance` with `setName('Rocket')`. One `RunSketchCommand` per registered sketch. Symfony `COMMAND` / `TERMINATE` become `SketchStarting` / `SketchFinished` on `app('signals')`. `RenderedInstance::handleSketch()` mirrors `handleInquiry()`. `withSketches()` feeds classes and paths. `ROCKET_BINARY` is `'launch'`.[^kernel][^command][^instance]

# Binaries

`php computer` → Console kernel. `php rocket` → Sketches kernel. This package has no `computer` or `rocket` script; `handleInquiry()` / `handleSketch()` are the two doors. A sketch name is not a computer command.[^tests]

# Not in 0.9

Middleware, run context, `pcntl` in the runner, mail hand-off into a sketch.[^runner]

[^runner]: SketchRunner
[^kernel]: Sketches kernel
[^command]: RunSketchCommand
[^instance]: RenderedInstance::handleSketch
[^tests]: Sketch kernel tests
