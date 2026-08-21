---
type: PHP Package
title: voyager/redis
description: Redis connection manager (Predis).
resource: ../../src/Voyager/Redis
tags: [php, package, voyager, redis]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Redis
    title: Redis package source (16 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Redis/composer.json
    title: voyager/redis composer.json
---

# Overview

16 PHP files under `src/Voyager/Redis/`. Upstream `v12.67.0`.
`RedisServiceProvider` is in `DefaultProviders`.
`tests/Redis/deferred/` holds connection/limiter tests that need a live
Redis. Active Redis tests are Pest v4.

# Related

- [voyager/cache](cache.md)

[^package-source]: Redis package source (16 PHP files)
[^package-manifest]: voyager/redis composer.json
