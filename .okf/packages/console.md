---
type: PHP Package
title: voyager/console
description: Console kernel, commands, and scheduling. The binary is named computer.
resource: ../../src/Voyager/Console
tags: [php, package, voyager, console, cli]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Console
    title: Console package source (78 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Console/composer.json
    title: voyager/console composer.json
---

# Overview

78 PHP files under `src/Voyager/Console/`. Upstream `v12.67.0`
`src/Illuminate/Console`. Wired through
`System\Providers\ConsoleSupportServiceProvider` in `DefaultProviders`.
The framework binary is **computer**, not artisan.

Some `tests/Console/deferred/` files still reference Orchestra\Testbench.

# Related

- [voyager/process](process.md)
- [Known gaps](/known-gaps.md)

[^package-source]: Console package source (78 PHP files)
[^package-manifest]: voyager/console composer.json
