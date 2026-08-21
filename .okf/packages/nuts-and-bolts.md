---
type: PHP Package
title: voyager/nuts-and-bolts
description: The general support package — string, number, date, and environment utilities plus the base contracts.
resource: ../../src/Voyager/NutsAndBolts
tags: [php, support, strings, numbers, env, package, voyager]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
stale_after: 2026-11-19
sources:
  - id: package-source
    resource: every PHP file under ../../src/Voyager/NutsAndBolts
    title: NutsAndBolts package source (5,065 lines across 14 files)
  - id: package-manifest
    resource: ../../src/Voyager/NutsAndBolts/composer.json
    title: voyager/nuts-and-bolts composer.json
  - id: reflection-run
    resource: runtime reflection and smoke test via ../../vendor/autoload.php
    title: Public API surface measurement and smoke test
---

# Overview

The catch-all support package, 5,065 lines across 14 files.[^package-source]
It is the only package whose directory is *not* listed under the
`Voyager\NutsAndBolts\` PSR-4 prefix — it resolves through the shorter
`Voyager\` prefix instead. See
[namespace and autoloading](/architecture/namespace-and-autoloading.md).

# Public surface

| Declaration | FQCN | Notes |
|-------------|------|-------|
| `Str` | `...\DataObjects\Str` | 114 public methods (113 static, plus `__call` from `Macroable`); 2,172 lines[^reflection-run] |
| `Stringable` | `...\DataObjects\Stringable` | 148 public methods; fluent wrapper implementing `JsonSerializable`, `ArrayAccess`, `Stringable`[^reflection-run] |
| `Number` | `...\DataObjects\Number` | 27 public methods (26 static); **requires `ext-intl`**[^reflection-run] |
| `Env` | `...\DataObjects\Env` | `enablePutenv`, `disablePutenv`, `extend`, `getRepository`, `get`, `getOrFail`[^reflection-run] |
| `Carbon` | `...\DataObjects\Carbon` | extends `Carbon\Carbon`; adds `setTestNow`, `createFromId`, `plus`, `minus`, `when`, `unless`, `dd`, `dump`[^reflection-run] |
| `Pluralizer` | `...\DataObjects\Pluralizer` | `plural`, `singular`, `useLanguage` — wraps `doctrine/inflector`[^reflection-run] |
| `HigherOrderTapProxy` | `...\DataObjects\HigherOrderTapProxy` | backs `tap($obj)->method()`[^package-source] |
| `Tappable` | `...\Concerns\Tappable` | `tap`[^reflection-run] |
| `Dumpable` | `...\Concerns\Dumpable` | `dd`, `dump` via `symfony/var-dumper`[^reflection-run] |
| `Arrayable`, `Jsonable` | `...\Contracts\*` | the two base contracts[^package-source] |

# Pluralization

`Pluralizer` wraps `doctrine/inflector ^2.0`, a declared requirement of both the
root package and `voyager/nuts-and-bolts`. It is upstream Laravel's implementation
unchanged, including `matchCase()` and the `$uncountable` list, so irregulars
resolve correctly in both directions: `child`/`children`, `person`/`people`,
`criterion`/`criteria`, `index`/`indices`, `sheep`/`sheep`.

`Str::plural()`, `Str::singular()`, `Str::pluralStudly()` and `Str::pluralPascal()`
all route through it. `Pluralizer::useLanguage()` swaps the inflector language and
resets the cached instance.

One case looks wrong and is not: `singular('axes')` returns `axe`, because *axes*
is the plural of both *axis* and *axe* and the inflector picks one. Laravel behaves
identically.

`Str::singular()` did not exist until 2026-08-19 even though
`Stringable::singular()` called it; it now forwards to `Pluralizer::singular()`.

# Env

`Env` wraps `vlucas/phpdotenv`. It lazily builds a `RepositoryInterface` from
`RepositoryBuilder::createWithDefaultAdapters()`, optionally adding
`PutenvAdapter` (enabled by default) and any adapters registered through
`Env::extend()`. Every mutator resets the cached repository to null so the next
`get()` rebuilds it.[^package-source]

`Env` reads the environment; it does **not** load a `.env` file. Something
higher in the stack must call phpdotenv's loader first — nothing in this
framework does.[^package-source]

# Number

Every `Number` method calls `ensureIntlExtensionIsInstalled()` first and throws
`RuntimeException` when `ext-intl` is absent. Defaults are `locale = 'en'` and
`currency = 'USD'`, both overridable per call and globally.[^package-source]

# Examples

```php
use Voyager\NutsAndBolts\DataObjects\{Str, Number, Env};

Str::slug('Hello Venusian World');   // 'hello-venusian-world'
Number::currency(1234.5);            // '$1,234.50'
Env::get('APP_DEBUG', false);
```

Both outputs above were produced by running the code.[^reflection-run]

# Declared dependencies

`NutsAndBolts/composer.json` requires `php ^8.4|^8.5|^8.6` — a wider range than
the root manifest's `^8.4|^8.5` — plus `ramsey/uuid`, `symfony/uid`,
`nesbot/carbon`, `vlucas/phpdotenv`, `league/commonmark`, `symfony/var-dumper`,
`voku/portable-ascii`, `voyager/macroable ^0.7.0`, `voyager/collections ^0.7.0`,
and `symfony/polyfill-php86 ^8.0.0`.[^package-manifest] The last three are
wrong — see the drift table in [known gaps](/known-gaps.md).

Requiring `voyager/collections` while
[voyager/collections](collections.md) depends on this package's contracts is a
package-level cycle; see [package split](/architecture/package-split.md).

# Related

- [Global helpers](/api/global-helpers.md) — `tap()`, `env()`, `with()`, `now()`, and the interval helpers.
- [Known gaps](/known-gaps.md) — `now()` is broken and `time.php` guards are wrong.

[^package-source]: NutsAndBolts package source (5,065 lines across 14 files)
[^package-manifest]: voyager/nuts-and-bolts composer.json
[^reflection-run]: Public API surface measurement and smoke test
