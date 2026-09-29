---
type: Module
title: Cache stores
description: Array, database, file, null and redis stores behind the cache manager; locks, tags, and the cache commands.
resource: src/Voyager/Cache/CacheManager.php
tags: [cache, stores, locks]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/Cache/CacheManager.php
    title: CacheManager
  - id: database
    resource: src/Voyager/Cache/DatabaseStore.php
    title: DatabaseStore
  - id: lock
    resource: src/Voyager/Cache/DatabaseLock.php
    title: DatabaseLock
  - id: config
    resource: config/cache.php
    title: cache config
  - id: commands
    resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
    title: ComputerServiceProvider
---

# Overview

`app('cache')` is `CacheManager`; `app('cache.store')` the default repository. Repository calls block; [async cache](async.md) is `async()`. No `Cache` facade. Default store `file`.[^manager][^config]

| Store | Class | Locks | Tags |
|---|---|---|---|
| array | `ArrayStore` | yes | yes |
| database | `DatabaseStore` | `DatabaseLock` | no |
| file | `FileStore` | `LockableFile` | no |
| null | `NullStore` | `NoLock` | yes |
| redis | `RedisStore` | `RedisLock` / `PhpRedisLock` | yes |

`memcached` is configured and has no driver: selecting it throws. APC, DynamoDB, memoized and failover stores are not ported.[^config]

# Database store

Rows `key`, `value`, `expiration` in `table` (default `cache`) on `connection`. Locks in `lock_table` (default `cache_locks`) on `lock_connection`, else the cache's connection. `lock_lottery` [2, 100] prunes expired locks; `lock_timeout` 86400 bounds a lock taken with no seconds.[^manager][^lock]

Values serialize; with a NUL byte, base64 on Postgres and SQLite. `serializable_classes` limits unserialize, as on the other stores. `forever()` is ten years, inside a 32-bit expiration column. An expired row reads as missing and is deleted.[^database]

Follows Voyager's other stores, not Laravel's database store: a missing or expired key's `increment()` / `decrement()` counts from zero and is kept forever; `forget()` says whether a row went. Increments hold the row `FOR UPDATE`; two callers racing a missing key both insert-if-absent, the loser counts on from the winner's row.[^database]

`DatabaseLock` inserts to acquire; an owned or expired row is taken over by update. `refresh()` extends an owned, unexpired lock.[^lock]

`make:cache-table` (alias `cache:table`) writes the `cache` and `cache_locks` migration.[^commands]

# Commands

`cache:clear`, `cache:forget`, and dev command `make:cache-table` are registered in `ComputerServiceProvider`.[^commands]

[^manager]: CacheManager
[^database]: DatabaseStore
[^lock]: DatabaseLock
[^config]: cache config
[^commands]: ComputerServiceProvider
