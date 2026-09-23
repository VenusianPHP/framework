---
type: Module
title: Cache defer
description: Repository::defer() queues a listed set of calls on the loop.
resource: src/Voyager/Cache/DeferredRepository.php
tags: [cache, defer]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: repo
    resource: src/Voyager/Cache/Repository.php
    title: Repository::defer
  - id: proxy
    resource: src/Voyager/Cache/DeferredRepository.php
    title: DeferredRepository
---

# Overview

`Repository::defer()` returns a `DeferredRepository`. Each method on that proxy is `$loop->defer(fn () => $repository->...(...))` and answers with a promise. The methods are `get`, `has`, `pull`, `put`, `add`, `forever`, `increment`, `decrement`, `forget`, and `remember`. There is no `__call`.[^repo]

The repository throws if `defer()` is called before `setLoop()`. The cache manager is what sets the loop. A repository built by hand needs `setLoop()` first.[^repo]

`Repository::flexible()` still calls a `defer()` helper that is not wired to `Loop::defer()`. That helper is `Voyager\NutsAndBolts\defer()`, out of this bundle's claim.

[^repo]: Repository::defer
[^proxy]: DeferredRepository
