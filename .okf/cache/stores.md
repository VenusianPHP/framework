---
type: Module
title: Cache stores
description: Array, file, null, and Redis stores. cache:clear and cache:forget exist as classes and are not registered.
resource: src/Voyager/Cache/CacheManager.php
tags: [cache]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
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

`CacheServiceProvider` registers the cache manager. `Repository::get()` and the other repository calls block. They do not join the loop's select. There is no `Cache` facade.[^provider]

# Stores in this tree

| Store | Class |
|---|---|
| array | `ArrayStore` |
| file | `FileStore`, locks via `LockableFile` |
| null | `NullStore` |
| redis | `RedisStore` |

Locks exist for those four stores (`NullStore` uses `NoLock`). Tags exist on array, null, and redis. `FileStore` does not implement `tags()`; `Repository::tags()` throws on it.

`ClearCommand` and `ForgetCommand` exist as `cache:clear` and `cache:forget`. Neither `CacheServiceProvider` nor `ComputerServiceProvider` registers them, so they are not on `php computer`'s list.

APC, database, DynamoDB, Memcached, memoized, and failover stores are not part of this port.

[^provider]: CacheServiceProvider
