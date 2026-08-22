---
type: PHP Package
title: voyager/sketches
description: Arduino-shaped sketch runtime — boot once, tick loop() until STOP, shutdown exactly once. The runner binary is named runner.
resource: ../../src/Voyager/Sketches
tags: [php, package, voyager, sketches, runner]
status: draft
generated: { by: agent:cursor-grok-4.6, at: 2026-08-23T00:00:00Z }
stale_after: 2026-11-22
sources:
  - id: package-source
    resource: ../../src/Voyager/Sketches
    title: Sketches package source (9 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Sketches/composer.json
    title: voyager/sketches composer.json
  - id: contracts
    resource: ../../src/Voyager/Contracts/Sketches
    title: Voyager\Contracts\Sketches (7 PHP files)
  - id: config
    resource: ../../config/sketches.php
    title: config/sketches.php (load and middleware only)
  - id: tests
    resource: ../../tests/Sketches
    title: Sketches Pest suite
  - id: system-kernel
    resource: ../../src/Voyager/System/Sketches/Kernel.php
    title: System sketches kernel (composition root)
  - id: upstream-07
    resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework/src/Fabricate/Sketches
    title: 0.7.x Fabricate\Sketches (answer key; Flow copy not ported)
---

# Overview

A sketch is the unit of a Venusian app. `php runner hello-world` boots it,
ticks `loop()` until `SketchLoopResult::STOP` (or `stop()` / SIGINT / SIGTERM),
then shuts down exactly once in `finally`.[^package-source]

**9** PHP files under `src/Voyager/Sketches/`. Contracts live in
`src/Voyager/Contracts/Sketches/` (**7** PHP files, including
`Attributes/Sketch`). Package name `voyager/sketches`. Requires
`voyager/contracts`, `voyager/nuts-and-bolts`, `voyager/console`, and
`voyager/pipeline` `^0.8.0`, plus Symfony Console and Finder. **Does not**
require `voyager/workflows`, `voyager/concurrency`, or System.[^package-manifest]

`extra.venusian` points at ScrapyardIO `src/Fabricate/Sketches`. The 0.7
`Flow/` tree (`BootSketchNode`, `TickSketchNode`, inlined AsyncNode) is **not**
in this package. Workflows stays a library a sketch *may* call from `loop()`.

# Runtime

`SketchRunner::run()` is a direct Arduino loop — no graph, no `SharedBag`, no
action strings:

1. `boot()` once.
2. `while (! $shouldStop)` call `loop()`; break on `SketchLoopResult::STOP`.
3. `shutdown()` exactly once in `finally` (including on throw).

`stop()` finishes the current tick (`shouldStop` is checked at the top of the
while). Constructor DI for sketch dependencies; `boot` / `loop` / `shutdown`
are not container-called.

# Discovery

`SketchRegistry` accepts:

- `register($class)` — requires `#[Sketch('name')]`
- `registerConvention($name, $class)` — kebab short name under
  `app/Runner/Sketches`
- `replace` / `replaceAs` — overwrite

`DiscoverSketches::within()` takes `basePath`, `appNamespace`, and `appPath`.
It does not call `app()`.

`config/sketches.php` has `load` and `middleware` only. No `concurrency`
key.[^config]

# Runner vs Computer

| Binary | Kernel | Symfony app title |
|--------|--------|-------------------|
| `php computer` | `System\Console\Kernel` | Computer |
| `php runner` | `System\Sketches\Kernel` | Venusian Runner |

Kernels and `Application::handleSketch()` live in System. `voyager/sketches`
does not `use Voyager\System\*`. `make:sketch` and `make:middleware` (app
`Runner/Middleware`) are Computer **dev** commands.

`SketchesServiceProvider` is deferred, wave 7 on `DefaultProviders`. It binds
the registry and runner only — not the kernel.

# Tests

`tests/Sketches/` is Pest v4 closures:

* `SketchRegistryTest.php` — attribute register, convention duplicate,
  `replace` / `replaceAs`
* `SketchRunnerTest.php` — N ticks then STOP; cooperative `stop()`; boot/loop
  throw → shutdown once; SIGTERM via `posix_kill` when `pcntl` exists
* `SketchMiddlewareTest.php` — `DispatchSketch` wraps the runner (`before` /
  `after`)

`tests/System/SketchMakeCommandTest.php` asserts `make:sketch` writes
`app/Runner/Sketches/{Name}.php` extending the app base.

There are **no** generic Workflows / Flow / AsyncFlow tests under
`tests/Sketches/`. Those belong to [voyager/workflows](workflows.md).

# Related

- [voyager/workflows](workflows.md) — graph library a sketch may call; not how
  the runner works
- [voyager/console](console.md) — Computer; `make:sketch` lives here via System
- [voyager/pipeline](pipeline.md) — onion around `SketchRunner::run()`
- [voyager/contracts](contracts.md)
- [Known gaps](/known-gaps.md)

[^package-source]: Sketches package source (9 PHP files)
[^package-manifest]: voyager/sketches composer.json
[^contracts]: Voyager\Contracts\Sketches (7 PHP files)
[^config]: config/sketches.php (load and middleware only)
[^tests]: Sketches Pest suite
[^system-kernel]: System sketches kernel (composition root)
[^upstream-07]: 0.7.x Fabricate\Sketches (answer key; Flow copy not ported)
