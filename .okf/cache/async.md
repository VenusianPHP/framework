---
type: Module
title: Async cache
description: Repository::async() answers every call with a promise; file and database stores run on worker pools, redis on the loop's own socket, array and null inline.
resource: src/Voyager/Cache/Async/AsyncRepository.php
tags: [cache, async, pool, redis]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: repository
    resource: src/Voyager/Cache/Async/AsyncRepository.php
    title: AsyncRepository
  - id: contract
    resource: src/Voyager/Cache/Async/AsyncStore.php
    title: AsyncStore
  - id: manager
    resource: src/Voyager/Cache/CacheManager.php
    title: CacheManager::asyncStore
  - id: file
    resource: src/Voyager/Cache/Async/FileAsyncStore.php
    title: FileAsyncStore
  - id: redis
    resource: src/Voyager/Cache/Async/RedisAsyncStore.php
    title: RedisAsyncStore
  - id: database
    resource: src/Voyager/Cache/Async/DatabaseAsyncStore.php
    title: DatabaseAsyncStore
---

# Overview

`$repository->async()` returns `AsyncRepository`: `get`, `has`, `pull`, `put`, `forever`, `add`, `increment`, `decrement`, `forget`, `remember`, each a promise. The manager gives every repository the loop through `useAsync()` when a loop is bound; without it `async()` throws `RuntimeException`. A tagged cache's `async()` throws `BadMethodCallException`: tag sets are kept by the blocking calls only.[^repository][^manager]

Decisions stay in the caller: defaults, TTL to seconds, a put with no time left becoming a forget, pull as get then forget, `remember`'s callback, and the cache signals, each dispatched once here. Only store I/O goes async.[^repository]

# Stores

| Store | Async I/O |
|---|---|
| file | `FileOperation` gigs on a worker pool; one gig per key at a time |
| database | `DatabaseOperation` gigs offloaded through the connection's lanes; get reads, the rest write |
| redis | commands on the connection's `RedisPipe` |
| array, null | none: runs inline, promise already settled |

Values encode and TTLs become expirations when the call is made, so a queued put stores what it was given, counted from the call. A put whose time ran out while queued stores nothing.[^file][^database]

A key's operations complete in call order. A blocking call on a key waits for the async ones made before it (`settle()`): per key for file and redis, per connection for database.[^contract][^database]

A database store's async call inside an open transaction on its connection rejects with `LogicException`: the worker would write outside the transaction.[^database]

Pool rule as elsewhere: `thread-workers`, else `process-workers`, else throw.

[^repository]: AsyncRepository
[^contract]: AsyncStore
[^manager]: CacheManager::asyncStore
[^file]: FileAsyncStore
[^redis]: RedisAsyncStore
[^database]: DatabaseAsyncStore
