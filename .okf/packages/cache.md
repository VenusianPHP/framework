---
type: PHP Package
title: voyager/cache
description: Cache manager, stores, and locks.
resource: ../../src/Voyager/Cache
tags: [php, package, voyager, cache]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Cache
    title: Cache package source (53 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Cache/composer.json
    title: voyager/cache composer.json
---

# Overview

55 PHP files under `src/Voyager/Cache/`. Upstream `v12.67.0`
`src/Illuminate/Cache`.[^package-manifest] `CacheServiceProvider` is in
`DefaultProviders`. Tests under `tests/Cache/` are Pest v4.

`DatabaseStore` (+ `DatabaseLock`) landed after `Voyager\Database` existed —
the last unported Cache store. `CacheManager::createDatabaseDriver()` wires
it the same way `redis`/`file` are wired; `config/cache.php` has a live
`database` store entry (`connection`, `table`, `lock_connection`,
`lock_table`). `CacheTableCommand` (`make:cache-table` / `cache:table`)
already generated the `cache` / `cache_locks` migration and needed no
change. Faithful port of `tests/Cache/CacheDatabaseStoreTest.php` lives at
`tests/Cache/CacheDatabaseStoreTest.php`; a supplementary
`CacheDatabaseStoreIntegrationTest.php` exercises the store and lock
against a real in-memory sqlite connection (`Voyager\Database\Capsule\Manager`),
since the upstream test only mocks the query builder.

# Related

- [voyager/redis](redis.md)
- [voyager/contracts](contracts.md)

[^package-source]: Cache package source (53 PHP files)
[^package-manifest]: voyager/cache composer.json
