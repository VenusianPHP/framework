---
type: PHP Package
title: voyager/collections
description: Eager and lazy collection pipelines plus the Arr helper, ported from illuminate/collections.
resource: ../../src/Voyager/Collections
tags: [php, collections, package, voyager]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Collections
    title: Collections package source (11 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Collections/composer.json
    title: voyager/collections composer.json
---

# Overview

11 PHP files under `src/Voyager/Collections/`. Classes live in
`Voyager\NutsAndBolts\` via the overlapping PSR-4 prefix. Provides
`Collection`, `LazyCollection`, `Arr`, `Enumerable`, `EnumeratesValues`,
`HigherOrderCollectionProxy`, and the two item-not-found exceptions.

`TransformsToResourceCollection` is still an empty trait used by
`Collection`. `Enumerable` remains `Voyager\NutsAndBolts\Contracts\Enumerable`
in this package — not in `voyager/contracts`.

`LazyCollection::make(mixed $items = [])` forwards to a constructor that
accepts `Closure`. `Arr::first` / `Arr::last` take `mixed $default`.

# Declared dependencies

`php ^8.4|^8.5`, `voyager/macroable ^0.8.0`, `voyager/contracts ^0.8.0`, and
**`voyager/conditionble ^0.8.0`** — typo for `conditionable`.[^package-manifest]
Recorded in [known gaps](/known-gaps.md). No `fabricate/*` require.

# Related

- [Global helpers](/api/global-helpers.md)
- [voyager/macroable](macroable.md)
- [voyager/contracts](contracts.md)

[^package-source]: Collections package source (11 PHP files)
[^package-manifest]: voyager/collections composer.json
