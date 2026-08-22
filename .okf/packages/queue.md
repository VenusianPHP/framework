---
type: PHP Package
title: voyager/queue
description: Queue manager and workers. Beanstalkd, SQS, and DynamoDB are cut; database driver source is present.
resource: ../../src/Voyager/Queue
tags: [php, package, voyager, queue]
status: draft
generated: { by: agent:cursor, at: 2026-08-22T21:30:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Queue
    title: Queue package source (95 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Queue/composer.json
    title: voyager/queue composer.json
---

# Overview

95 PHP files under `src/Voyager/Queue/`. Upstream `v12.67.0`.
`QueueServiceProvider` is in `DefaultProviders`. Connectors: Null, Sync,
Deferred, Background, Failover, Database, Redis. `config/queue.php` defaults
to `sync`.

Beanstalkd, SQS, and DynamoDB are absent. Database driver classes exist;
their tests stay in `tests/Queue/deferred/` (README still says wave 6).
Active Queue tests and the four deferred files are Pest v4.
`CallQueuedClosure` imports `System\Bus\Dispatchable`.

# Related

- [Known gaps](/known-gaps.md)
- [voyager/database](database.md)

[^package-source]: Queue package source (95 PHP files)
[^package-manifest]: voyager/queue composer.json
