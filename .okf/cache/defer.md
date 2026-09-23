---
type: Module
title: Cache defer
description: Cache::defer() queues every call on the loop.
resource: src/Voyager/Cache/DeferredRepository.php
tags: [cache, defer]
status: draft
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

`Repository::defer()` returns a `DeferredRepository`. Every call on that proxy is `$loop->defer(fn () => $repository->...(...))` and answers with a promise.[^repo]

The repository throws if `defer()` is called before `setLoop()`. The cache manager is what sets the loop. A repository built by hand needs `setLoop()` first.[^repo]

`Repository::flexible()` still calls a `defer()` helper that is not wired to `Loop::defer()`. That helper is out of this bundle's claim.

[^repo]: Repository::defer
[^proxy]: DeferredRepository
