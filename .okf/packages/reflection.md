---
type: PHP Package
title: voyager/reflection
description: Reflector, a static helper for interrogating callables, parameter types, and class attributes.
resource: ../../src/Voyager/Reflection
tags: [php, reflection, package, voyager]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
sources:
  - id: package-source
    resource: every PHP file under ../../src/Voyager/Reflection
    title: Reflection package source (345 lines across 3 files)
  - id: package-manifest
    resource: ../../src/Voyager/Reflection/composer.json
    title: voyager/reflection composer.json
  - id: reflection-run
    resource: runtime reflection over the Reflection package classes
    title: Public API surface measurement
---

# Overview

345 lines across three files. It is the only package whose classes are not all
under `Voyager\NutsAndBolts\`: `Reflector` is namespaced
`Voyager\Reflection`.[^package-source] See
[namespace and autoloading](/architecture/namespace-and-autoloading.md).

| Declaration | FQCN | File |
|-------------|------|------|
| `Reflector` | `Voyager\Reflection\Reflector` | `Reflector.php` (217 lines) |
| `ReflectsClosures` | `Voyager\NutsAndBolts\Concerns\ReflectsClosures` | `Concerns/ReflectsClosures.php` (128 lines) |
| — | — | `Helpers/helpers.php` is **empty** (0 bytes) but is still listed as an autoload `files` entry[^package-source] |

# Reflector

Six public static methods:[^reflection-run]

| Method | Purpose |
|--------|---------|
| `isCallable($var, bool $syntaxOnly = false)` | `is_callable` that also handles `[$obj, 'method']` with `__call` |
| `getClassAttribute(...)` | Read a `ReflectionAttribute` off a class |
| `getParameterClassName(ReflectionParameter $p)` | Resolve a parameter's class type, unwrapping `self`/`static` |
| `getParameterClassNames(ReflectionParameter $p)` | Same, for union types |
| `isParameterSubclassOf(...)` | Type-hint subclass check |
| `isParameterBackedEnumWithStringBackingType(...)` | Backed-enum detection |

# ReflectsClosures

Provides `firstClosureParameterType`, `closureParameterTypes`, and related
helpers for deriving event/listener types from closure signatures.

**Fixed 2026-08-19.** It was declared `class ReflectsClosures` rather than
`trait`, with all-protected methods — zero public surface, and `use
ReflectsClosures;` would not compile.[^reflection-run] The
[0.7.x reference implementation](/reference/upstream-0-7-x.md) declares it a
`trait`, settling it as a port regression rather than a redesign. It is now a
trait, verified by composing it into a class and calling
`firstClosureParameterType()`.

# Declared dependencies

`Reflection/composer.json` requires `php ^8.4|^8.5` and
**`fabricate/collection ^0.6|^0.7`** — not a typo but a stale **0.7.x** package
name, from back when the framework was namespaced `Fabricate`; the current
dependency is `voyager/collections` (`ReflectsClosures` uses
`Voyager\NutsAndBolts\Collection`). Its `branch-alias` is also `0.7.x-dev` while
the other four packages say `0.8.x-dev`.[^package-manifest] See
[known gaps](/known-gaps.md).

# Related

- [voyager/collections](collections.md) — the real dependency.
- [Laravel lineage](/architecture/laravel-lineage.md) — ported from `Illuminate\Support\Reflector`.

[^package-source]: Reflection package source (345 lines across 3 files)
[^package-manifest]: voyager/reflection composer.json
[^reflection-run]: Public API surface measurement
