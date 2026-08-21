---
type: API Surface
title: Global helper functions
description: The 27 global functions the framework autoloads into the global namespace, plus one namespaced internal helper.
resource: ../../src/Voyager/*/Helpers
tags: [php, helpers, functions, global-namespace, api]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T20:00:00Z }
stale_after: 2026-11-19
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json autoload.files
    author: human:angel
    last_modified: 2026-08-19
  - id: helper-files
    resource: every file under ../../src/Voyager/*/Helpers
    title: Helper source files
  - id: smoke-test
    resource: function_exists checks and invocations against ../../vendor/autoload.php
    title: Helper availability verification run
---

# Overview

The root manifest autoloads six `files` entries, which is how these functions
reach the global namespace on every request:[^root-composer]

```
src/Voyager/Collections/Helpers/helpers.php     (292 lines, 10 functions)
src/Voyager/Collections/Helpers/functions.php   (29 lines, 1 namespaced function)
src/Voyager/NutsAndBolts/Helpers/helpers.php    (121 lines, 7 functions)
src/Voyager/NutsAndBolts/Helpers/functions.php  (1 line, empty — just `<?php`)
src/Voyager/NutsAndBolts/Helpers/time.php       (108 lines, 10 functions)
src/Voyager/Reflection/Helpers/helpers.php      (0 bytes, empty)
```

Every function is wrapped in a `function_exists` guard, so a host application
that already defines `collect()` or `env()` wins.[^helper-files] The guards in
`time.php` named the wrong (namespaced) functions until 2026-08-19 — see below.

# Collections helpers

From `Collections/Helpers/helpers.php`:[^helper-files]

| Function | Signature | Purpose |
|----------|-----------|---------|
| `collect` | `(Arrayable\|array\|null $value = []): Collection` | Build a [`Collection`](/packages/collections.md) |
| `data_get` | `(mixed $target, int\|array\|string\|null $key, mixed $default = null): mixed` | Dot-path read across arrays and objects, `*` wildcards supported |
| `data_set` | `(mixed &$target, array\|string $key, mixed $value, bool $overwrite = true): mixed` | Dot-path write |
| `data_fill` | `(mixed &$target, array\|string $key, mixed $value): mixed` | `data_set` with `$overwrite = false` |
| `data_has` | `(mixed $target, int\|array\|string\|null $key): bool` | Dot-path existence check |
| `data_forget` | `(mixed &$target, int\|array\|string\|null $key): mixed` | Dot-path unset |
| `head` | `($array)` | First element |
| `last` | `($array)` | Last element |
| `value` | `(mixed $value, ...$args): mixed` | Invoke if closure, else return as-is |
| `when` | `($condition, $value, $default = null)` | Conditional `value()` |

# Support helpers

From `NutsAndBolts/Helpers/helpers.php`:[^helper-files]

| Function | Purpose |
|----------|---------|
| `tap($value, ?callable $callback = null): mixed` | Run a side effect and return `$value`; without a callback returns a `HigherOrderTapProxy` |
| `with($value, ?callable $callback = null)` | Return `$value`, optionally piped through `$callback` |
| `env(string $key, mixed $default = null): mixed` | Delegates to [`Env::get`](/packages/nuts-and-bolts.md) |
| `class_basename(object\|string $class): string` | Class name without namespace |
| `class_uses_recursive(object\|string $class): array` | Traits of a class, its parents, and their traits |
| `trait_uses_recursive(object\|string $trait): array` | Traits of a trait, recursively |
| `windows_os(): bool` | `PHP_OS_FAMILY === 'Windows'` |

# Time helpers

From `NutsAndBolts/Helpers/time.php`. Nine of the ten wrap
`Carbon\CarbonInterval` and work correctly — `microseconds`, `milliseconds`,
`seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `years`, each taking
`int|float` (or `int` for weeks and up) and returning a
`CarbonInterval`.[^helper-files] Verified: `seconds(5)` returns
`Carbon\CarbonInterval`.[^smoke-test]

**`now()` — fixed 2026-08-19.** It called
`Voyager\NutsAndBolts\MagicAliases\Date::now()`, a class that does not exist in
this tree, and threw `Error: Class ... not found` on every call.[^smoke-test] It
is now wired to `Carbon::now()` and returns a
`Voyager\NutsAndBolts\DataObjects\Carbon`.

This is **interim**. `MagicAliases` is the facade layer: `Date` resolves a
`'date'` container binding, so it cannot return until the System layer exists.
The seam is noted in the source — see
[the 0.7.x reference](/reference/upstream-0-7-x.md).

Two further defects in the same file were fixed alongside it: all ten
`function_exists` guards named `Voyager\NutsAndBolts\<fn>` while the file
declares **global** functions (so they could never fire, and a second include
would fatal), and `enum_value()` was called unqualified from the global namespace
where it does not resolve. Detail in [known gaps](/known-gaps.md).

# Namespaced internal helper

`Collections/Helpers/functions.php` declares `namespace Voyager\NutsAndBolts\Helpers;`
and one `@internal` function:[^helper-files]

```php
Voyager\NutsAndBolts\Helpers\enum_value(mixed $value, ?callable $default = null): mixed
```

It unwraps a `BackedEnum` to its `->value` and a `UnitEnum` to its `->name`.
`Collection` and `LazyCollection` import it with a `use function` statement.
It is the **only** function in the framework that is namespaced rather than
global.[^helper-files]

# Verification

All 27 global functions and `Voyager\NutsAndBolts\Helpers\enum_value` were
confirmed defined after loading `vendor/autoload.php`; the namespaced
`Voyager\NutsAndBolts\now` referenced by `time.php`'s guards was confirmed
**not** defined.[^smoke-test]

# Related

- [voyager/collections](/packages/collections.md)
- [voyager/nuts-and-bolts](/packages/nuts-and-bolts.md)
- [Namespace and autoloading](/architecture/namespace-and-autoloading.md)

[^root-composer]: venusian/framework composer.json autoload.files
[^helper-files]: Helper source files
[^smoke-test]: Helper availability verification run
