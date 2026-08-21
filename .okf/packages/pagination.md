---
type: PHP Package
title: voyager/pagination
description: Length-aware and cursor paginators. No service provider.
resource: ../../src/Voyager/Pagination
tags: [php, package, voyager, pagination]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Pagination
    title: Pagination package source (7 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Pagination/composer.json
    title: voyager/pagination composer.json
---

# Overview

7 PHP files under `src/Voyager/Pagination/`. Upstream `v12.67.0`.
`DefaultProviders` notes it needs no provider (the upstream one loaded Blade
views and read the page from an HTTP request). Four `loadMorph` tests remain
in `tests/Pagination/deferred/`.

# Related

- [voyager/database](database.md)
- [Dependency direction](/architecture/dependency-direction.md)

[^package-source]: Pagination package source (7 PHP files)
[^package-manifest]: voyager/pagination composer.json
