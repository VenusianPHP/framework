---
type: PHP Package
title: voyager/concurrency
description: Concurrent driver that fans work out through Process / Console.
resource: ../../src/Voyager/Concurrency
tags: [php, package, voyager, concurrency, process]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Concurrency
    title: Concurrency package source (6 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Concurrency/composer.json
    title: voyager/concurrency composer.json
---

# Overview

6 PHP files under `src/Voyager/Concurrency/`. Upstream `v12.67.0`.
`ConcurrencyServiceProvider` is in `DefaultProviders`. The `Concurrency`
magic alias must resolve `ConcurrencyManager::class` (not the string
`'concurrency'`). `config/concurrency.php` exists.

# Related

- [voyager/process](process.md)
- [voyager/console](console.md)

[^package-source]: Concurrency package source (6 PHP files)
[^package-manifest]: voyager/concurrency composer.json
