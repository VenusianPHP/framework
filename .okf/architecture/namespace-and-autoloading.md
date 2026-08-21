---
type: Convention
title: Namespace and autoloading scheme
description: How Voyager\NutsAndBolts\* class names resolve across five physical directories via overlapping PSR-4 prefixes.
tags: [psr-4, autoloading, namespaces, composer]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T18:20:00Z }
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json (version 0.8.0)
    author: human:angel
    last_modified: 2026-08-19
  - id: fqcn-map
    resource: every namespace and class declaration under ../../src/Voyager
    title: Declared FQCN to file-path map
  - id: smoke-test
    resource: runtime reflection executed against ../../vendor/autoload.php
    title: Autoload verification run
---

# Overview

Physical layout is by **package**; logical namespace is by **role**. A class in
`Collections/Concerns/` and a class in `Macroable/Concerns/` both live under
`Voyager\NutsAndBolts\Concerns`. Two overlapping PSR-4 prefixes make that work.

# The two prefixes

```yaml
"Voyager\\": "src/Voyager"
"Voyager\\NutsAndBolts\\":
  - "src/Voyager/Macroable/"
  - "src/Voyager/Collections/"
  - "src/Voyager/Conditionable/"
  - "src/Voyager/Reflection/"
```

Note what is **not** in the second list: `src/Voyager/NutsAndBolts/` itself.[^root-composer]
It is reached through the shorter `Voyager\` prefix instead —
`Voyager\NutsAndBolts\DataObjects\Str` → `src/Voyager/NutsAndBolts/DataObjects/Str.php`.

Resolution therefore relies on Composer trying the longest matching prefix
first, sweeping all four of its directories, and **falling back to the shorter
prefix** when none of them holds the file. `Voyager\NutsAndBolts\Concerns\Tappable`
is the illustrative case: it is absent from all four listed directories and is
found at `src/Voyager/NutsAndBolts/Concerns/Tappable.php` via `Voyager\`.[^fqcn-map]

This was verified end to end — all 26 declarations resolve under
`vendor/autoload.php`.[^smoke-test]

# Declared FQCN to file map

| FQCN | File (under `src/Voyager/`) |
|------|------------------------------|
| `Voyager\NutsAndBolts\Collection` | `Collections/Collection.php` |
| `Voyager\NutsAndBolts\LazyCollection` | `Collections/LazyCollection.php` |
| `Voyager\NutsAndBolts\HigherOrderWhenProxy` | `Conditionable/HigherOrderWhenProxy.php` |
| `Voyager\NutsAndBolts\Concerns\Conditionable` | `Conditionable/Concerns/Conditionable.php` |
| `Voyager\NutsAndBolts\Concerns\Macroable` | `Macroable/Concerns/Macroable.php` |
| `Voyager\NutsAndBolts\Concerns\EnumeratesValues` | `Collections/Concerns/EnumeratesValues.php` |
| `Voyager\NutsAndBolts\Concerns\TransformsToResourceCollection` | `Collections/Concerns/TransformsToResourceCollection.php` |
| `Voyager\NutsAndBolts\Concerns\ReflectsClosures` | `Reflection/Concerns/ReflectsClosures.php` |
| `Voyager\NutsAndBolts\Concerns\Tappable` | `NutsAndBolts/Concerns/Tappable.php` |
| `Voyager\NutsAndBolts\Concerns\Dumpable` | `NutsAndBolts/Concerns/Dumpable.php` |
| `Voyager\NutsAndBolts\Contracts\Enumerable` | `Collections/Contracts/Enumerable.php` |
| `Voyager\NutsAndBolts\Contracts\CanBeEscapedWhenCastToString` | `Collections/Contracts/CanBeEscapedWhenCastToString.php` |
| `Voyager\NutsAndBolts\Contracts\Arrayable` | `NutsAndBolts/Contracts/Arrayable.php` |
| `Voyager\NutsAndBolts\Contracts\Jsonable` | `NutsAndBolts/Contracts/Jsonable.php` |
| `Voyager\NutsAndBolts\DataObjects\Arr` | `Collections/DataObjects/Arr.php` |
| `Voyager\NutsAndBolts\DataObjects\HigherOrderCollectionProxy` | `Collections/DataObjects/HigherOrderCollectionProxy.php` |
| `Voyager\NutsAndBolts\DataObjects\{Str,Stringable,Number,Env,Carbon,Pluralizer,HigherOrderTapProxy}` | `NutsAndBolts/DataObjects/` |
| `Voyager\NutsAndBolts\Exceptions\{ItemNotFoundException,MultipleItemsFoundException}` | `Collections/Exceptions/` |
| `Voyager\Reflection\Reflector` | `Reflection/Reflector.php` |

# Rules to follow

1. **`Voyager\NutsAndBolts\` is the namespace for everything except `Reflector`.**
   `Voyager\Reflection\Reflector` is the single class outside it, resolved via
   the `Voyager\` prefix.[^fqcn-map]
2. **Namespace by role, not by directory.** `DataObjects\` for value objects and
   static utility classes, `Concerns\` for traits, `Contracts\` for interfaces,
   `Exceptions\` for exceptions, `Helpers\` for functions. Root-level
   `Voyager\NutsAndBolts\` holds only `Collection`, `LazyCollection`, and
   `HigherOrderWhenProxy`.
3. **Adding a directory to a package means updating the root manifest** if the
   package is one of the four listed under the `Voyager\NutsAndBolts\` prefix.
4. **A name collision across two of the four directories is unresolvable** — the
   first directory in list order wins silently. Keep leaf filenames unique
   across `Macroable/`, `Collections/`, `Conditionable/`, and `Reflection/`.

# Related

- [Package split](package-split.md) — why the directories exist.
- [Global helpers](/api/global-helpers.md) — the `files` autoload entries.

[^root-composer]: venusian/framework composer.json (version 0.8.0)
[^fqcn-map]: Declared FQCN to file-path map
[^smoke-test]: Autoload verification run
