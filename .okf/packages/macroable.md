---
type: PHP Package
title: voyager/macroable
description: The Macroable trait, letting third parties attach methods to a class at runtime.
resource: ../../src/Voyager/Macroable
tags: [php, traits, extensibility, package, voyager]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Macroable/Concerns/Macroable.php
    title: Macroable trait
  - id: package-manifest
    resource: ../../src/Voyager/Macroable/composer.json
    title: voyager/macroable composer.json
---

# Overview

One PHP file: `Voyager\NutsAndBolts\Concerns\Macroable`. Requires only PHP
`^8.4|^8.5`. Used across Collections, Str, Stringable, Number, and many
later components.

Wave 5 recorded that typing `Macroable::__call` while leaving
`Manager::__call` untyped fatals any class that extends `Manager` and uses
the trait — [port hazards](/architecture/port-hazards.md).

# Packaging notes

This is the only split manifest that still declares `homepage` /
`support` URLs, and they still point at `ScrapyardIO/framework`.[^package-manifest]
`branch-alias` is `0.8.x-dev`. No `fabricate/*` consumers remain; Collections
now requires `voyager/macroable ^0.8.0`.

# Related

- [voyager/collections](collections.md)
- [voyager/conditionable](conditionable.md)

[^package-source]: Macroable trait
[^package-manifest]: voyager/macroable composer.json
