---
type: PHP Package
title: voyager/http
description: HTTP client only. No incoming request stack, UploadedFile, or FileFactory.
resource: ../../src/Voyager/Http
tags: [php, package, voyager, http, client]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Http
    title: Http package source (18 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Http/composer.json
    title: voyager/http composer.json
---

# Overview

18 PHP files, all under `src/Voyager/Http/Client/`. Manifest description:
"The Voyager HTTP client package." Upstream `v12.67.0` (client slice).

`Http\UploadedFile` and `Http\Testing\FileFactory` do **not** exist.
Validation file rules still run against Symfony's `File`/`UploadedFile`.
Three file-rule tests stay in `tests/Validation/deferred/`.

# Related

- [voyager/validation](validation.md)
- [Known gaps](/known-gaps.md)

[^package-source]: Http package source (18 PHP files)
[^package-manifest]: voyager/http composer.json
