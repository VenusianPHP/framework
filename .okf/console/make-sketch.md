---
type: Module
title: make:sketch
description: Dev command that writes an empty sketch class into the directory rocket scans, extending the app's own abstract Sketch when it has one.
resource: src/Voyager/Core/Console/SketchMakeCommand.php
tags: [console, computer, sketches, generator]
status: draft
generated: { by: claude-opus-5-5, at: "2026-10-07T00:00:00Z" }
sources:
  - id: command
    resource: src/Voyager/Core/Console/SketchMakeCommand.php
    title: SketchMakeCommand
  - id: stub
    resource: src/Voyager/Core/Console/stubs/sketch.stub
    title: sketch.stub
  - id: kernel
    resource: src/Voyager/Core/Sketches/Kernel.php
    title: Sketches Kernel
  - id: test
    resource: tests/Sketches/MakeSketchTest.php
    title: Pest coverage
---

# Use

```bash
php computer make:sketch BlinkLed          # rocket name: blink-led
php computer make:sketch Leds/Pulse        # subdirectory, still scanned
php computer make:sketch BlinkLed --force  # overwrite
```

Registered in `ComputerServiceProvider::$dev_commands`. `GeneratorCommand` subclass: reserved names refused, existing file kept unless `--force`.[^command]

# Where it writes

Sketch kernel decides, not `app_path()`:[^command][^kernel]

* Root namespace + directory = `Kernel::discoveryNamespace()` / `discoveryPath()` (set by `discoverUsing()`, else app namespace + app path). Same pair discovery maps files with.
* Target = `sketchPaths()` entry under that root. `setup()` always scans `app/Console/Sketches`; a path the app named in `withSketches()` beats it (skeleton → `app/Runner/Sketches`). Paths need not exist; `..` segments resolve.
* None under root → `Console\Sketches` (the `withSketches()` default dir) + warning: rocket won't find it until its dir or class goes in `withSketches()`.

# Base class

Walks from the new class's namespace up to the root; first abstract `Sketch` subclass named `Sketch` wins (skeleton's `App\Runner\Sketches\Sketch`). None → `Voyager\Sketches\Sketch`. Same namespace → no import. New class itself named `Sketch` → base imported `as BaseSketch`.[^command][^test]

Body = `$description`, empty `boot()` / `shutdown()`, `loop(array $mail = [])` returning `CONTINUE`. `stub:publish` copies `sketch.stub`; a published `stubs/sketch.stub` wins.[^stub]

[^command]: SketchMakeCommand
[^stub]: sketch.stub
[^kernel]: Sketches Kernel
[^test]: Pest coverage
