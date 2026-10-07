---
type: Module
title: Sketch runner
description: Rocket kernel behind php rocket - console bootstrappers plus DeliverMailToSketches, one command per sketch, starting and finished signals.
resource: src/Voyager/Core/Sketches/Kernel.php
tags: [sketches, rocket, kernel]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: kernel
    resource: src/Voyager/Core/Sketches/Kernel.php
    title: Sketches kernel
  - id: deliver
    resource: src/Voyager/Core/Sketches/DeliverMailToSketches.php
    title: DeliverMailToSketches
  - id: command
    resource: src/Voyager/Sketches/Console/RunSketchCommand.php
    title: RunSketchCommand
  - id: instance
    resource: src/Voyager/Core/RenderedInstance.php
    title: RenderedInstance::handleSketch
  - id: factory
    resource: src/Voyager/Core/Bootstrap/ConfigFactory.php
    title: ConfigFactory::withSketches
  - id: tests
    resource: tests/Sketches/SketchesTest.php
    title: Sketches tests
---

# Kernel

`Voyager\Core\Sketches\Kernel`. The console kernel's bootstrappers with `DeliverMailToSketches` right after `LoadConfiguration`: it sets `io-pools.event_loop.mail_handlers.default` to `sketch` before any provider builds the loop, so the running sketch receives the loop's mail.[^kernel][^deliver]

Rocket is a `ComputerConsoleInstance` named `Rocket` with one `RunSketchCommand` per registered sketch; the command resolves the sketch, hands it the console IO, and returns `SketchRunner::run()`.[^kernel][^command]

Symfony `COMMAND` / `TERMINATE` become `SketchStarting` / `SketchFinished` on `app('signals')`. A throw is reported and rendered; exit 1.[^kernel]

`ROCKET_BINARY` is `'rocket'`. `ConfigFactory::withSketches()` feeds classes and paths (default `app/Console/Sketches`). `make:sketch` writes into the first of those paths under the discovery root ([make:sketch](../console/make-sketch.md)).[^kernel][^factory]

# Binaries

`php computer` → console kernel through `handleInquiry()`. `php rocket` → this kernel through `RenderedInstance::handleSketch()`. This package ships neither script. A sketch name is not a computer command.[^instance][^tests]

# Exit

`STOP` → 0 (`SketchExitStatus::SUCCESS`). SIGINT / SIGTERM stop the loop: 130 / 143. Frames are in [sketch runtime](runtime.md).

[^kernel]: Sketches kernel
[^deliver]: DeliverMailToSketches
[^command]: RunSketchCommand
[^instance]: RenderedInstance::handleSketch
[^factory]: ConfigFactory::withSketches
[^tests]: Sketches tests
