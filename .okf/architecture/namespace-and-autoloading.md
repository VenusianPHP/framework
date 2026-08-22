---
type: Convention
title: Namespace and autoloading scheme
description: How Voyager\ and the overlapping Voyager\NutsAndBolts\ PSR-4 prefixes resolve 1081 PHP files across 33 component directories.
tags: [psr-4, autoloading, namespaces, composer]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verified: { by: agent:framework-auditor, at: 2026-08-22T21:46:31Z }
verification_key: 'agent:framework-auditor@e4450c2d96ec2451305ce21fc13030c7a581a000'
stale_after: 2026-11-22
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json autoload
  - id: src-tree
    resource: every PHP file under ../../src/Voyager
    title: 33 directories, 1081 PHP files
---

# Overview

Physical layout is by **package directory** under `src/Voyager/<Name>/`.
Logical namespace is usually `Voyager\<Name>\`, except the NutsAndBolts
family, which shares `Voyager\NutsAndBolts\` across five directories.

# The two prefixes

From the root manifest:[^root-composer]

```yaml
"Voyager\\": "src/Voyager"
"Voyager\\NutsAndBolts\\":
  - "src/Voyager/Macroable/"
  - "src/Voyager/Collections/"
  - "src/Voyager/Conditionable/"
  - "src/Voyager/Reflection/"
```

`src/Voyager/NutsAndBolts/` itself is **not** in the second list. It is
reached through `Voyager\` —
`Voyager\NutsAndBolts\DataObjects\Str` → `src/Voyager/NutsAndBolts/DataObjects/Str.php`.

Composer tries the longest prefix first, then falls back. A name collision
across Macroable / Collections / Conditionable / Reflection resolves silently
to list order.

`Voyager\Reflection\Reflector` is the family member **not** under
`Voyager\NutsAndBolts\`.

# Files autoload

Root `autoload.files` (order as written):[^root-composer]

1. `src/Voyager/NutsAndBolts/functions.php` — namespaced `Voyager\NutsAndBolts\*`
2. `src/Voyager/Filesystem/functions.php` — namespaced `Voyager\Filesystem\join_paths`
3. `src/Voyager/Collections/Helpers/helpers.php` — global collection helpers
4. `src/Voyager/Collections/Helpers/functions.php` — `Voyager\NutsAndBolts\Helpers\enum_value`
5. `src/Voyager/NutsAndBolts/Helpers/helpers.php` — global support helpers
6. `src/Voyager/NutsAndBolts/Helpers/functions.php` — empty
7. `src/Voyager/NutsAndBolts/Helpers/time.php` — global `now()` and intervals
8. `src/Voyager/Reflection/Helpers/helpers.php` — empty
9. `src/Voyager/System/helpers.php` — global `app()`, `config()`, `dispatch()`, …

See [global helpers](/api/global-helpers.md).

# Dev autoload

`Tests\\` → `tests/`. Extra `autoload-dev.files`: Enums and fixture
`functions.php` files that PSR-4 cannot carry, including
`tests/Database/Enums.php` and `tests/Validation/Enums.php`.[^root-composer]

# Rules

1. New component code goes in `src/Voyager/<Package>/` with namespace
   `Voyager\<Package>\`, unless it is a NutsAndBolts-family type.
2. Family types still namespace by role: `DataObjects\`, `Concerns\`,
   `Contracts\`, `Exceptions\`.
3. Adding a directory to Macroable / Collections / Conditionable / Reflection
   means checking the `Voyager\NutsAndBolts\` prefix list.
4. Keep leaf filenames unique across those four directories.
5. Do not invent a "26 declarations" map — the tree is 1081 PHP files.[^src-tree]

# Related

- [Package split](package-split.md)
- [Global helpers](/api/global-helpers.md)

[^root-composer]: venusian/framework composer.json autoload
[^src-tree]: 33 directories, 1081 PHP files
