---
type: PHP Package
title: voyager/collections
description: Eager and lazy collection pipelines plus the Arr helper, ported from illuminate/collections.
resource: ../../src/Voyager/Collections
tags: [php, collections, package, voyager]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
stale_after: 2026-11-19
sources:
  - id: package-source
    resource: every PHP file under ../../src/Voyager/Collections
    title: Collections package source (7,498 lines across 12 files)
  - id: package-manifest
    resource: ../../src/Voyager/Collections/composer.json
    title: voyager/collections composer.json
  - id: reflection-run
    resource: runtime reflection over the Collections classes via ../../vendor/autoload.php
    title: Public API surface measurement
---

# Overview

The largest package in the framework at 7,498 lines across 12 files.[^package-source]
It provides two `Enumerable` implementations — an eager array-backed
`Collection` and a generator-backed `LazyCollection` — sharing their behavior
through the `EnumeratesValues` trait.

# Public surface

| Declaration | FQCN | Size |
|-------------|------|------|
| `Collection` | `Voyager\NutsAndBolts\Collection` | 183 public methods[^reflection-run] |
| `LazyCollection` | `Voyager\NutsAndBolts\LazyCollection` | 1,367 lines[^package-source] |
| `Enumerable` | `Voyager\NutsAndBolts\Contracts\Enumerable` | 148 declared methods[^reflection-run] |
| `EnumeratesValues` | `Voyager\NutsAndBolts\Concerns\EnumeratesValues` | 1,218 lines, shared implementation[^package-source] |
| `Arr` | `Voyager\NutsAndBolts\DataObjects\Arr` | 65 public methods (64 static)[^reflection-run] |
| `HigherOrderCollectionProxy` | `Voyager\NutsAndBolts\DataObjects\HigherOrderCollectionProxy` | backs `$collection->map->name`[^package-source] |
| `CanBeEscapedWhenCastToString` | `Voyager\NutsAndBolts\Contracts\CanBeEscapedWhenCastToString` | escaping opt-in[^package-source] |
| `ItemNotFoundException`, `MultipleItemsFoundException` | `Voyager\NutsAndBolts\Exceptions\*` | thrown by `sole()`, `firstOrFail()`[^package-source] |
| `TransformsToResourceCollection` | `Voyager\NutsAndBolts\Concerns\TransformsToResourceCollection` | **empty trait**, see [known gaps](/known-gaps.md)[^package-source] |

`LazyCollection::make()` accepts a `Closure` returning a generator, an array, an
`Arrayable`, or null. Passing a *generator object* is rejected on purpose —
pass the generator function instead.[^package-source]

# Composition

```
Collection      implements ArrayAccess, CanBeEscapedWhenCastToString, Enumerable
                uses EnumeratesValues, Macroable, TransformsToResourceCollection

LazyCollection  implements CanBeEscapedWhenCastToString, Enumerable
                uses EnumeratesValues, Macroable

Enumerable      extends Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
```

Both are generically typed with `@template TKey of array-key` and
`@template-covariant TValue`.[^package-source]

`Enumerable` extends `Arrayable` and `Jsonable`, which live in
[nuts-and-bolts](/packages/nuts-and-bolts.md), and `LazyCollection` uses `Carbon`
from the same package. Both are legal: the five foundation packages form one
family that may inter-depend freely, and those contracts are deliberately the
*family's own* rather than framework-wide ones. See
[dependency direction](/architecture/dependency-direction.md).

# Examples

```php
require 'vendor/autoload.php';

collect([1, 2, 3])->map(fn ($n) => $n * 2)->sum();   // 12

use Voyager\NutsAndBolts\LazyCollection;

LazyCollection::make(function () {
    $handle = fopen('huge.log', 'r');
    while (($line = fgets($handle)) !== false) {
        yield $line;
    }
})->filter(fn ($line) => str_contains($line, 'ERROR'))->take(10)->all();
```

Method semantics follow Laravel's — see [Laravel lineage](/architecture/laravel-lineage.md).

**Fixed 2026-08-19:** `implode()`, `groupBy()`, and `where()` previously
mishandled objects implementing native `\Stringable`, and
`LazyCollection::make()` rejected the `Closure` form its own constructor accepts.
Both are corrected; reproductions and detail in [known gaps](/known-gaps.md).

# Declared dependencies

`Collections/composer.json` requires `php ^8.4|^8.5` and
**`fabricate/macroable ^0.8.0`** — a wrong vendor name for what is really
`voyager/macroable`.[^package-manifest] Recorded in [known gaps](/known-gaps.md).

# Related

- [Global helpers](/api/global-helpers.md) — `collect()`, `data_get()`, `head()`, `last()`, `value()`.
- [voyager/macroable](macroable.md) — the `Macroable` trait both classes use.
- [voyager/nuts-and-bolts](nuts-and-bolts.md) — `Arrayable`, `Jsonable`, `Carbon`.

[^package-source]: Collections package source (7,498 lines across 12 files)
[^package-manifest]: voyager/collections composer.json
[^reflection-run]: Public API surface measurement
