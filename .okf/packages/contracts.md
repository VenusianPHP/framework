---
type: PHP Package
title: voyager/contracts
description: Framework-wide interfaces (Venusian's illuminate/contracts). Includes Contracts/Sketches.
resource: ../../src/Voyager/Contracts
tags: [php, package, voyager, contracts, interfaces]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verified: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verification_key: 'agent:framework-auditor@3e93855e843921190adc69bcfd272ecb538d94b3'
stale_after: 2026-11-22
sources:
  - id: package-source
    resource: ../../src/Voyager/Contracts
    title: Contracts package source (127 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Contracts/composer.json
    title: voyager/contracts composer.json
---

# Overview

**127** PHP files under `src/Voyager/Contracts/`. Package name
`voyager/contracts`. Autoload `Voyager\Contracts\`. Requires PHP
`^8.4|^8.5`, `psr/container`, `psr/simple-cache`. No `extra.venusian` block.

Subdirectories: Broadcasting, Bus, Cache, Concurrency, Config, Console,
Database, Debug, Encryption, Events, Filesystem, Hashing, JsonSchema, Log,
Notifications, NutsAndBolts, Pagination, Pipeline, Process, Queue, Redis,
Sketches (7 files: `Sketch`, `SketchLoopResult`, `SketchExitStatus`,
`SketchException`, `SketchRegistry`, `Kernel`, `Attributes/Sketch`),
System, Translation, Validation, Vessel, Workflows (6 files:
`AsyncRuntime`, `Awaitable`, `AsyncRunnable`, `RuntimeAware`,
`WorkflowRuntimeException`, `WorkflowLogicException`).

There is **no** `Contracts/Auth` or `Contracts/View`. `System/helpers.php`
still aliases those names; nothing in that file uses the aliases.

`Voyager\Contracts\NutsAndBolts\{Arrayable,Jsonable,...}` live here.
`Enumerable` does **not** — it remains
`Voyager\NutsAndBolts\Contracts\Enumerable` in Collections.

# Related

- [Dependency direction](/architecture/dependency-direction.md)
- [voyager/sketches](sketches.md)
- [Known gaps](/known-gaps.md)

[^package-source]: Contracts package source (127 PHP files)
[^package-manifest]: voyager/contracts composer.json
