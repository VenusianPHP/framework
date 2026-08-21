---
type: PHP Package
title: voyager/filesystem
description: Filesystem manager over League Flysystem.
resource: ../../src/Voyager/Filesystem
tags: [php, package, voyager, filesystem, flysystem]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Filesystem
    title: Filesystem package source (8 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Filesystem/composer.json
    title: voyager/filesystem composer.json
---

# Overview

8 PHP files under `src/Voyager/Filesystem/`. Upstream `v12.67.0`.
`FilesystemServiceProvider` is in `DefaultProviders`.
`Filesystem/functions.php` declares namespaced `join_paths`.
`tests/Filesystem/deferred/` still holds Testbench-touching files.

# Related

- [Global helpers](/api/global-helpers.md)

[^package-source]: Filesystem package source (8 PHP files)
[^package-manifest]: voyager/filesystem composer.json
