---
type: PHP Package
title: voyager/testing
description: Test fakes and concerns (QueueFake, NotificationFake, PendingBatchFake, …).
resource: ../../src/Voyager/Testing
tags: [php, package, voyager, testing, fakes]
status: draft
generated: { by: agent:cursor, at: 2026-08-22T21:30:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Testing
    title: Testing package source (34 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Testing/composer.json
    title: voyager/testing composer.json
---

# Overview

34 PHP files under `src/Voyager/Testing/`. Upstream `v12.67.0`. Fakes
include `QueueFake`, `BatchFake`, `BatchRepositoryFake`, `PendingBatchFake`,
`PendingChainFake`, `NotificationFake`. Several files import
`Voyager\System\*` (upward edge).

`tests/Testing/deferred/`: `ConfigShowCommandTest` still extends
`Orchestra\Testbench\TestCase` and is left as PHPUnit on purpose;
`InteractsWithDatabaseTest` and `TestDatabasesTest` remain parked after
the MagicAlias Mockery fix and Database landing.

# Related

- [Known gaps](/known-gaps.md)
- [Dependency direction](/architecture/dependency-direction.md)

[^package-source]: Testing package source (34 PHP files)
[^package-manifest]: voyager/testing composer.json
