---
type: PHP Package
title: voyager/conditionable
description: The Conditionable trait and its higher-order proxy, providing fluent when()/unless() chaining.
resource: ../../src/Voyager/Conditionable
tags: [php, traits, fluent-api, package, voyager]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T18:20:00Z }
sources:
  - id: package-source
    resource: every PHP file under ../../src/Voyager/Conditionable
    title: Conditionable package source (137 lines across 2 files)
  - id: package-manifest
    resource: ../../src/Voyager/Conditionable/composer.json
    title: voyager/conditionable composer.json
  - id: reflection-run
    resource: runtime reflection over the Conditionable classes
    title: Public API surface measurement
---

# Overview

The smallest package: 137 lines across two files, requiring only PHP
`^8.4|^8.5`.[^package-source]

| Declaration | FQCN | File |
|-------------|------|------|
| `Conditionable` | `Voyager\NutsAndBolts\Concerns\Conditionable` | `Concerns/Conditionable.php` (57 lines) |
| `HigherOrderWhenProxy` | `Voyager\NutsAndBolts\HigherOrderWhenProxy` | `HigherOrderWhenProxy.php` (80 lines) |

Note that `HigherOrderWhenProxy` sits at the package root, not under a
subdirectory, so its namespace is bare `Voyager\NutsAndBolts` — the same
namespace level as `Collection` and `LazyCollection`. See
[namespace and autoloading](/architecture/namespace-and-autoloading.md).

# Public surface

`Conditionable` exposes exactly two methods, `when` and `unless`.[^reflection-run]
Called without a callback, each returns a `HigherOrderWhenProxy` so the next
method call in the chain is conditionally forwarded or swallowed.[^package-source]

# Examples

```php
$query->when($request->filled('name'), fn ($q) => $q->whereName($request->name));

// higher-order form — no callback, proxy decides whether to forward
$builder->when($sorted)->orderBy('created_at');
```

# Consumers

`Stringable` and `Carbon` both use this trait.[^package-source] Note that
`Collection` and `LazyCollection` do **not** — they declare their own `when`
and `unless` on `EnumeratesValues` instead. See
[voyager/collections](collections.md).

# Related

- [voyager/macroable](macroable.md) — the sibling single-trait package.
- [Laravel lineage](/architecture/laravel-lineage.md) — ported from `Illuminate\Support\Traits\Conditionable`.

[^package-source]: Conditionable package source (137 lines across 2 files)
[^package-manifest]: voyager/conditionable composer.json
[^reflection-run]: Public API surface measurement
