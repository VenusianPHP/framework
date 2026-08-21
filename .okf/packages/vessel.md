---
type: PHP Package
title: voyager/vessel
description: Service container (Illuminate\Container port).
resource: ../../src/Voyager/Vessel
tags: [php, package, voyager, container, vessel]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Vessel
    title: Vessel package source (18 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Vessel/composer.json
    title: voyager/vessel composer.json
---

# Overview

18 PHP files under `src/Voyager/Vessel/`. Manifest: "The Voyager Service
Container package." `extra.venusian`: `laravel/framework@v12.67.0`
`src/Illuminate/Container`, ported 2026-08-19.[^package-manifest]
Provides `psr/container-implementation`. System's `Application` sits above
this. Tests under `tests/Vessel/` are Pest v4; one file remains in
`tests/Vessel/deferred/`.

# Related

- [Dependency direction](/architecture/dependency-direction.md)
- [voyager/contracts](contracts.md)

[^package-source]: Vessel package source (18 PHP files)
[^package-manifest]: voyager/vessel composer.json
