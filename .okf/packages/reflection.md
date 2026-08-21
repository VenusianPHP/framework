---
type: PHP Package
title: voyager/reflection
description: Reflector, a static helper for interrogating callables, parameter types, and class attributes.
resource: ../../src/Voyager/Reflection
tags: [php, reflection, package, voyager]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Reflection
    title: Reflection package source (3 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Reflection/composer.json
    title: voyager/reflection composer.json
---

# Overview

3 PHP files. `Reflector` is namespaced `Voyager\Reflection` (the family
exception). `ReflectsClosures` is a **trait** at
`Voyager\NutsAndBolts\Concerns\ReflectsClosures`. `Helpers/helpers.php` is
empty and still listed in `autoload.files`.

# Declared dependencies

Requires `voyager/collection ^0.8` — singular; the real package is
`voyager/collections`.[^package-manifest] `branch-alias` is now `0.8.x-dev`
(the old `0.7.x-dev` / `fabricate/collection` pins are gone).

# Related

- [voyager/collections](collections.md)
- [Laravel lineage](/architecture/laravel-lineage.md)

[^package-source]: Reflection package source (3 PHP files)
[^package-manifest]: voyager/reflection composer.json
