---
type: Module
title: Cache stores
description: Array, file, null, and Redis stores, plus cache:clear and cache:forget.
resource: src/Voyager/Cache/CacheManager.php
tags: [cache]
status: draft
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: manager
    resource: src/Voyager/Cache/CacheManager.php
    title: CacheManager
  - id: provider
    resource: src/Voyager/Cache/CacheServiceProvider.php
    title: CacheServiceProvider
---

# Overview

`CacheServiceProvider` boots the cache manager. `Cache::get()` and the other repository calls block. They do not join the loop's select.[^provider]

# Stores in this tree

| Store | Class |
|---|---|
| array | `ArrayStore` |
| file | `FileStore`, locks via `LockableFile` |
| null | `NullStore` |
| redis | `RedisStore` |

Locks and tags exist for those stores. Console commands in this tree are `cache:clear` and `cache:forget`.

APC, database, DynamoDB, Memcached, memoized, and failover stores are not part of this port.

[^provider]: CacheServiceProvider
