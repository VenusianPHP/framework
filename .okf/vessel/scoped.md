---
type: Module
title: Scoped bindings
description: scoped() shares an instance within one fiber, and once in the main context; a loop task shares its starter's scope; bags go with their fibers.
resource: src/Voyager/Vessel/Concerns/ContainerBinding.php
tags: [vessel, container, scoped, fiber]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: binding
    resource: src/Voyager/Vessel/Concerns/ContainerBinding.php
    title: ContainerBinding
  - id: panel
    resource: src/Voyager/Vessel/ControlPanel.php
    title: ControlPanel
  - id: contract
    resource: src/Voyager/Contracts/Vessel/TheServiceContainer.php
    title: TheServiceContainer
  - id: bind
    resource: src/Voyager/Contracts/Vessel/Attributes/Bind.php
    title: Bind attribute
  - id: test
    resource: tests/Vessel/ScopedPerFiberTest.php
    title: ScopedPerFiberTest
---

# Overview

`scoped($abstract, $concrete)` binds a shared instance per scope: one per fiber, one for the main context (`Fiber::getCurrent() === null`). `registerScoped()` delegates to it. A closure abstract scopes each of its return types. A class marked `#[Scoped]`, or a contract with `#[Bind]` and `#[Scoped]`, is scoped on first resolve.[^panel][^binding][^bind]

Scoped abstracts are a set (`$scoped_instances`). Each fiber's instances sit in a bag in a `WeakMap` keyed by the fiber; the main context has its own bag. A bag goes when its fiber is collected, finished or abandoned mid-suspend, including one holding its own fiber.[^binding][^test]

`resolve()` reads and stores a scoped abstract in the current bag; plain singletons stay in `$instances`, shared everywhere. `registerInstance()` on a scoped abstract writes the current bag only. `isResolved()` sees the current bag. Binding an abstract again drops its instances from every bag and its scoped mark; `offsetUnset()` the same; `flush()` empties every bag.[^panel][^binding]

# Reset and sharing

`forgetScopedInstances()` gives the current fiber (or the main context) a fresh bag; other fibers keep theirs. `queue:work` calls it between jobs.[^binding]

`shareScopeWith(Fiber $child)` hands a fiber not yet started the current bag, so both resolve the same scoped instances and see each other's writes. A started fiber throws `LogicException`. Both are on `TheServiceContainer`. The app's loop calls it for every fiber `async()` starts ([Async](/io-pools/async.md)).[^binding][^contract]

[^binding]: ContainerBinding
[^panel]: ControlPanel
[^contract]: TheServiceContainer
[^bind]: Bind attribute
[^test]: ScopedPerFiberTest
