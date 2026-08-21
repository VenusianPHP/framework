---
type: PHP Package
title: voyager/conditionable
description: The Conditionable trait and its higher-order proxy, providing fluent when()/unless() chaining.
resource: ../../src/Voyager/Conditionable
tags: [php, traits, fluent-api, package, voyager]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Conditionable
    title: Conditionable package source (2 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Conditionable/composer.json
    title: voyager/conditionable composer.json
---

# Overview

2 PHP files, PHP `^8.4|^8.5` only.[^package-source]

| Declaration | FQCN | File |
|-------------|------|------|
| `Conditionable` | `Voyager\NutsAndBolts\Concerns\Conditionable` | `Concerns/Conditionable.php` |
| `HigherOrderWhenProxy` | `Voyager\NutsAndBolts\HigherOrderWhenProxy` | `HigherOrderWhenProxy.php` |

`Stringable` and `Carbon` use the trait. `Collection` / `LazyCollection`
declare their own `when` / `unless` on `EnumeratesValues`.

`Collections/composer.json` misspells this package as `voyager/conditionble`.

# Related

- [voyager/macroable](macroable.md)
- [Laravel lineage](/architecture/laravel-lineage.md)

[^package-source]: Conditionable package source (2 PHP files)
[^package-manifest]: voyager/conditionable composer.json
