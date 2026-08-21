---
type: PHP Package
title: voyager/macroable
description: The Macroable trait, letting third parties attach methods to a class at runtime.
resource: ../../src/Voyager/Macroable
tags: [php, traits, extensibility, package, voyager]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T18:20:00Z }
sources:
  - id: package-source
    resource: ../../src/Voyager/Macroable/Concerns/Macroable.php
    title: Macroable trait (112 lines)
  - id: package-manifest
    resource: ../../src/Voyager/Macroable/composer.json
    title: voyager/macroable composer.json
  - id: reflection-run
    resource: runtime reflection over Voyager\NutsAndBolts\Concerns\Macroable
    title: Public API surface measurement
---

# Overview

One trait, 112 lines, zero dependencies beyond PHP `^8.4|^8.5`.[^package-source]
It is the most widely used piece of the framework: `Collection`,
`LazyCollection`, `Str`, `Stringable`, and `Number` all use it.

`Voyager\NutsAndBolts\Concerns\Macroable` → `src/Voyager/Macroable/Concerns/Macroable.php`.

# Public surface

| Method | Purpose |
|--------|---------|
| `macro(string $name, callable $macro)` | Register a single method[^reflection-run] |
| `mixin(object $mixin, bool $replace = true)` | Register every method of a mixin object[^reflection-run] |
| `hasMacro(string $name)` | Check registration[^reflection-run] |
| `flushMacros()` | Clear all registered macros[^reflection-run] |
| `__call`, `__callStatic` | Dispatch; closures are rebound to the instance or class[^reflection-run] |

# Examples

```php
use Voyager\NutsAndBolts\Collection;

Collection::macro('toUpper', function () {
    return $this->map(fn ($v) => strtoupper($v));
});

collect(['a', 'b'])->toUpper()->all();   // ['A', 'B']
```

# Packaging notes

This is the one manifest that declares `homepage` and `support` URLs, both
pointing at `ScrapyardIO/framework`.[^package-manifest] Its `branch-alias` is
`0.8.x-dev`, matching the monorepo.

`Collections/composer.json` refers to this package as **`fabricate/macroable`**
and `NutsAndBolts/composer.json` pins it to `^0.7.0` — both wrong. See
[known gaps](/known-gaps.md).

# Related

- [voyager/collections](collections.md), [voyager/nuts-and-bolts](nuts-and-bolts.md) — consumers.
- [voyager/conditionable](conditionable.md) — the sibling single-trait package.

[^package-source]: Macroable trait (112 lines)
[^package-manifest]: voyager/macroable composer.json
[^reflection-run]: Public API surface measurement
