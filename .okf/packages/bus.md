---
type: PHP Package
title: voyager/bus
description: Synchronous and queued command bus, including batches.
resource: ../../src/Voyager/Bus
tags: [php, package, voyager, bus, queue]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Bus
    title: Bus package source (16 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Bus/composer.json
    title: voyager/bus composer.json
---

# Overview

16 PHP files under `src/Voyager/Bus/`. Upstream `v12.67.0`
`src/Illuminate/Bus`.[^package-manifest] `BusServiceProvider` is in
`DefaultProviders`.

`DatabaseBatchRepository::find(): ?Batch` still falls off the end when no
row is found — [known gaps](/known-gaps.md). `tests/Bus/deferred/BusBatchTest.php`
stays excluded for that and a `PendingChain` type mismatch.

`Dispatcher` and `ChainedBatch` import `Voyager\System\Bus\*` (upward edge).

# Related

- [voyager/queue](queue.md)
- [Dependency direction](/architecture/dependency-direction.md)

[^package-source]: Bus package source (16 PHP files)
[^package-manifest]: voyager/bus composer.json
