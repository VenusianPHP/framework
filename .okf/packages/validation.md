---
type: PHP Package
title: voyager/validation
description: Validator and rules. Rules\Can, HTTP ValidationException surface, and precognition are cut.
resource: ../../src/Voyager/Validation
tags: [php, package, voyager, validation]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Validation
    title: Validation package source (45 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Validation/composer.json
    title: voyager/validation composer.json
---

# Overview

45 PHP files under `src/Voyager/Validation/`. Upstream `v12.67.0`.
`ValidationServiceProvider` is in `DefaultProviders`. Tests (including
`ValidationValidatorTest.php`) are Pest v4.

Cuts: `Rules\Can`, HTTP fields on `ValidationException`, precognition
hook. File rules have no `Http\UploadedFile` subject — three tests stay
deferred. Database presence tests also stay in
`tests/Validation/deferred/` even though Database has landed.

# Related

- [Known gaps](/known-gaps.md)
- [voyager/http](http.md)

[^package-source]: Validation package source (45 PHP files)
[^package-manifest]: voyager/validation composer.json
