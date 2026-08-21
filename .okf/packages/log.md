---
type: PHP Package
title: voyager/log
description: Log manager and context repository.
resource: ../../src/Voyager/Log
tags: [php, package, voyager, log, monolog]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Log
    title: Log package source (11 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Log/composer.json
    title: voyager/log composer.json
---

# Overview

11 PHP files under `src/Voyager/Log/`. Upstream `v12.67.0`.
`LogServiceProvider` is in `DefaultProviders`. `config/logging.php` exists.
`tests/Log/deferred/{ContextTest,LogManagerTest}.php` still reference
Orchestra\Testbench. `ContextTest` Suit serialize lengths are stale
(`E:31:` / `E:43:` vs 29 / 41 byte names).

# Related

- [Known gaps](/known-gaps.md)

[^package-source]: Log package source (11 PHP files)
[^package-manifest]: voyager/log composer.json
