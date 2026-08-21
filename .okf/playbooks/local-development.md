---
type: Playbook
title: Local development
description: How to install, smoke-test, and extend the Venusian framework working tree, including the missing test harness.
tags: [development, testing, pest, composer, onboarding]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T21:00:00Z }
stale_after: 2026-11-19
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json
    author: human:angel
    last_modified: 2026-08-19
  - id: gitattributes
    resource: ../../.gitattributes
    title: Repository export-ignore rules
  - id: tree-state
    resource: the working tree at ../../ (tests/, config/, .github/, README.md, AGENTS.md)
    title: Repository state as observed
  - id: smoke-test
    resource: composer and pest invocations in the working tree
    title: Toolchain verification run
---

# Context

You are working on the **Support foundation** of a Laravel-like framework aimed
at sketch-based CLI workflows — see [overview](/overview.md). Most of what is
here is ported Laravel code, so upstream behavior is the baseline and
[divergences](/architecture/laravel-lineage.md) are what need documenting.

# Prerequisites

- PHP `8.4` or `8.5`.[^root-composer]
- `ext-intl`, required by [`Number`](/packages/nuts-and-bolts.md).
- Composer 2.
- `pestphp/pest-plugin` must be allowed — it already is, via
  `config.allow-plugins`.[^root-composer]

# Install

```bash
composer install
```

`composer.lock` is gitignored, so every install resolves fresh against
`minimum-stability: dev` with `prefer-stable: true`.[^root-composer] Expect
dependency versions to drift between machines; pin deliberately if that matters.

# Run the tests

```bash
vendor/bin/pest
```

184 tests, 301 assertions, covering the whole Support foundation. CI runs the
same command on PHP 8.4 and 8.5 via `.github/workflows/tests.yml`; `intl` must be
present or every `Number` test fails.

`tests/Regression/PortHazardsTest.php` pins the defects fixed on 2026-08-19.
Those all failed *silently* before, so treat a regression there as significant
rather than cosmetic — see [port hazards](/architecture/port-hazards.md).

# Smoke test

Quicker than the suite when you only want to know the tree loads:

```bash
php -r 'require "vendor/autoload.php";
  echo collect([1,2,3])->map(fn($n) => $n * 2)->sum(), PHP_EOL;
  echo Voyager\NutsAndBolts\DataObjects\Str::slug("Hello Venusian World"), PHP_EOL;
  echo Voyager\NutsAndBolts\DataObjects\Number::currency(1234.5), PHP_EOL;'
```

Expected output: `12`, `hello-venusian-world`, `$1,234.50`.[^smoke-test]

Do **not** include `now()` in a smoke test — it fatals. See
[known gaps](/known-gaps.md).

# Test layout

`phpunit.xml` points a single `Framework` suite at `./tests`. Pest bootstraps
through `tests/Pest.php`, which provides `nativeStringable()` — an object
implementing only PHP's native `\Stringable`, used to prove the Collections
paths treat any stringable alike.

```
tests/Collections/{Arr,Collection,LazyCollection}Test.php
tests/NutsAndBolts/{Str,Stringable,Number,Conditionable,Macroable,Helpers}Test.php
tests/Reflection/ReflectorTest.php
tests/Regression/PortHazardsTest.php
```

`.gitattributes` export-ignores `/tests`, `/phpunit.xml` and `/.github`, so none
of it ships in a Composer dist tarball.

Writing the suite is what surfaced the `Arr::first()` and `chunk()` defects — so
when adding coverage, call each method the way Laravel documents it rather than
the way the local signature allows. See
[port hazards](/architecture/port-hazards.md).

# Adding a class

1. Put the file in the directory of the package that owns it —
   `src/Voyager/<Package>/`.
2. Namespace it by **role**, not by directory: `DataObjects\` for value objects
   and static utilities, `Concerns\` for traits, `Contracts\` for interfaces,
   `Exceptions\` for exceptions. See
   [namespace and autoloading](/architecture/namespace-and-autoloading.md).
3. Keep the leaf filename unique across `Macroable/`, `Collections/`,
   `Conditionable/`, and `Reflection/` — a collision across those four resolves
   silently to whichever comes first in the PSR-4 directory list.
4. Adding a **new global helper file** means adding it to the root manifest's
   `autoload.files` *and* the sub-package manifest's, then re-running
   `composer dump-autoload`.
5. Adding a cross-package dependency means updating the sub-package manifest —
   the monorepo autoloader will not tell you that you forgot. See
   [package split](/architecture/package-split.md).

# Where the knowledge lives

This `.okf/` bundle is export-ignored, so it ships with the git repository but
not with a composer dist tarball.[^gitattributes] `README.md` and `AGENTS.md` are
both empty; treat [overview](/overview.md) as the current entry point and update
this bundle when the tree changes.

[^root-composer]: venusian/framework composer.json
[^gitattributes]: Repository export-ignore rules
[^tree-state]: Repository state as observed
[^smoke-test]: Toolchain verification run
