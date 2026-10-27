---
type: Module
title: Config
description: config() repository; a write inside a fiber stays with that fiber and its loop tasks, read over the items; main-context writes stay global.
resource: src/Voyager/Config/Repository.php
tags: [config, fiber, scoped]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: repository
    resource: src/Voyager/Config/Repository.php
    title: Repository
  - id: overrides
    resource: src/Voyager/Config/Overrides.php
    title: Overrides
  - id: load
    resource: src/Voyager/Core/Bootstrap/LoadConfiguration.php
    title: LoadConfiguration
  - id: test
    resource: tests/Core/FiberConfigTest.php
    title: FiberConfigTest
---

# Overview

`app('config')` is `Repository`, built by `LoadConfiguration` from `config/` or the cached file. Dot-notation `get`, `getMany`, `has`, typed getters, `set`, `push`, `prepend`, `all`, array access. `config([...])` sets.[^repository][^load]

# Writes inside a fiber

`overridesUsing(?Closure)` names where writes go: the closure returns an `Overrides`, or null to write the items. `LoadConfiguration` binds `Overrides` with `scoped()` and returns it inside a fiber, null in the main context. So a fiber's writes stay its own; main-context writes change the items for everyone.[^repository][^load]

Reads in a fiber with writes take them over the items: a nested write replaces only that key (`probe.changed` leaves `probe.kept`). Main-context writes made later still show through for keys the fiber did not write. `push`, `prepend` and array access go through `get`/`set`, so they follow the same rule.[^repository][^test]

The effective items are built once per fiber and reused; a new write in the fiber, or a change to the items (a version counter on `Repository`), builds them again. `Overrides::builds()` counts.[^overrides]

A loop task shares its starter's scope ([Scoped bindings](/vessel/scoped.md)), so it reads its starter's writes, and its own writes are visible to the starter: the same bag, as for every scoped instance. `forgetScopedInstances()` in a fiber drops its writes. `config:cache` reads `all()` in the main context: the items, no fiber's writes.[^load][^test]

A `Repository` with no resolver writes its items from anywhere.[^repository]

[^repository]: Repository
[^overrides]: Overrides
[^load]: LoadConfiguration
[^test]: FiberConfigTest
