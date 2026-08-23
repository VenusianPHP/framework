---
type: API Surface
title: Global helper functions
description: Functions injected by the root composer autoload.files entries — collection helpers, support helpers, time helpers, namespaced NutsAndBolts/Filesystem helpers, and System helpers.
resource: ../../composer.json
tags: [php, helpers, functions, global-namespace, api]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verified: { by: agent:framework-auditor, at: 2026-08-23T03:08:15Z }
verification_key: 'agent:framework-auditor@3e93855e843921190adc69bcfd272ecb538d94b3'
stale_after: 2026-11-21
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json autoload.files
  - id: helper-files
    resource: the ten files listed in autoload.files
    title: Helper source files
---

# Overview

Root `autoload.files` lists ten paths.[^root-composer] Several functions are
declared more than once behind `function_exists` guards; the first loaded
definition wins. Do not cite "27 global helpers" as current — System alone
declares more than that.

# Autoload order

| File | Namespace | Role |
|------|-----------|------|
| `NutsAndBolts/functions.php` | `Voyager\NutsAndBolts` | `defer`, `php_binary`, `computer_binary`, `now`, interval helpers |
| `Filesystem/functions.php` | `Voyager\Filesystem` | `join_paths` |
| `Collections/Helpers/helpers.php` | global | `collect`, `data_*`, `head`, `last`, `value`, `when` |
| `Collections/Helpers/functions.php` | `Voyager\NutsAndBolts\Helpers` | `enum_value` |
| `NutsAndBolts/Helpers/helpers.php` | global | `tap`, `env`, `blank`, `optional`, `retry`, `str`, … |
| `NutsAndBolts/Helpers/functions.php` | — | empty (`<?php` only) |
| `NutsAndBolts/Helpers/time.php` | global | `now`, interval helpers (Carbon) |
| `Reflection/Helpers/helpers.php` | — | empty (0 bytes) |
| `System/helpers.php` | global | `app`, `config`, `dispatch`, `validator`, `broadcast`, … |
| `Graph/Helpers/functions.php` | global | `cypher`, `cypher_one`, `cypher_run`, `neo4j_connection` |

# Collections helpers (global)

`collect`, `data_get`, `data_set`, `data_fill`, `data_has`, `data_forget`,
`head`, `last`, `value`, `when`.[^helper-files]

`collect()` now takes `mixed $value = []` (wave-0 narrowing to
`Arrayable|array|null` was widened).

# Support helpers (global)

From `NutsAndBolts/Helpers/helpers.php`:[^helper-files]

`tap`, `class_basename`, `env`, `windows_os`, `with`, `class_uses_recursive`,
`trait_uses_recursive`, `append_config`, `blank`, `e`, `filled`, `fluent`,
`literal`, `object_get`, `once`, `optional`, `preg_replace_array`, `retry`,
`str`, `throw_if`, `throw_unless`, `transform`.

# Time helpers

`NutsAndBolts/Helpers/time.php` declares **global** `now()` plus
`microseconds` … `years`. Global `now()` is the Carbon interim:

```php
return Carbon::now(enum_value($tz));
```

`NutsAndBolts/functions.php` declares the same names **inside**
`namespace Voyager\NutsAndBolts` and `now()` there calls `Date::now()`.
`System/helpers.php` also declares global `now()` / `today()` via `Date`.
Guards keep the first definition.

# System helpers (global)

From `System/helpers.php` (non-exhaustive): `app`, `app_path`, `base_path`,
`bcrypt`, `broadcast`, `broadcast_if`, `broadcast_unless`, `cache`, `config`,
`config_path`, `context`, `database_path`, `decrypt`, `defer`, `dispatch`,
`dispatch_sync`, `encrypt`, `event`, `fake`, `info`, `lang_path`, `logger`,
`logs`, `now`, `report`, `report_if`, `report_unless`, `rescue`, `resolve`,
`storage_path`, `today`, `trans`, `trans_choice`, `__`, `validator`.

That file `use`s `Voyager\Contracts\Auth\Factory` and
`Voyager\Contracts\View\{Factory,View}` — those contracts are not on disk,
and no function in the file references the aliases. See
[known gaps](/known-gaps.md).

# Namespaced helpers

- `Voyager\NutsAndBolts\Helpers\enum_value` — unwraps backed/unit enums.
- `Voyager\NutsAndBolts\{defer,now,php_binary,computer_binary,…}`
- `Voyager\Filesystem\join_paths`

# Related

- [voyager/nuts-and-bolts](/packages/nuts-and-bolts.md)
- [voyager/collections](/packages/collections.md)
- [Namespace and autoloading](/architecture/namespace-and-autoloading.md)

[^root-composer]: venusian/framework composer.json autoload.files
[^helper-files]: Helper source files
