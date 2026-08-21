---
type: PHP Package
title: voyager/nuts-and-bolts
description: The general support package — strings, numbers, dates, environment, Manager, ServiceProvider, and the concrete magic aliases.
resource: ../../src/Voyager/NutsAndBolts
tags: [php, support, strings, numbers, env, package, voyager]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/NutsAndBolts
    title: NutsAndBolts package source (73 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/NutsAndBolts/composer.json
    title: voyager/nuts-and-bolts composer.json
---

# Overview

73 PHP files under `src/Voyager/NutsAndBolts/`. This directory is **not** on
the `Voyager\NutsAndBolts\` PSR-4 list — it resolves through `Voyager\`.
See [namespace and autoloading](/architecture/namespace-and-autoloading.md).

Surface includes `DataObjects\{Str,Stringable,Number,Env,Carbon,Pluralizer}`,
`Manager`, `ServiceProvider`, `Fluent`, `MessageBag`, `Sleep`, `Uri`,
`Defer\*`, and the concrete magic aliases in `MagicAliases/`.
`Pluralizer` wraps `doctrine/inflector ^2.0` (not a stub).
`Number` requires `ext-intl`.

`extra.venusian` records `laravel/framework@v12.67.0`
`src/Illuminate/Support`, ported 2026-08-19.[^package-manifest]

# Declared dependencies

PHP **`^8.4|^8.5|^8.6`** — wider than the root `^8.4|^8.5`. Requires
`voyager/{collections,conditionable,contracts,macroable,magic-aliases,reflection} ^0.8.0`
plus Carbon, phpdotenv, ramsey/uuid, etc. No `fabricate/*`. The old
`voyager/macroable ^0.7.0` / `polyfill-php86 ^8.0.0` pins are gone.

# Related

- [Global helpers](/api/global-helpers.md)
- [voyager/magic-aliases](magic-aliases.md)
- [Known gaps](/known-gaps.md)

[^package-source]: NutsAndBolts package source (73 PHP files)
[^package-manifest]: voyager/nuts-and-bolts composer.json
