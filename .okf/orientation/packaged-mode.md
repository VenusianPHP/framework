---
type: Concept
title: Packaged mode
description: An app whose base path is inside a phar keeps storage, database and bootstrap caches in the user's data directory, seeded from the phar on first run.
resource: src/Voyager/Core/PackagedPaths.php
tags: [core, paths, phar, build]
status: draft
generated: { by: claude-fable-5-1, at: "2026-10-08T00:00:00Z" }
sources:
  - id: paths
    resource: src/Voyager/Core/PackagedPaths.php
    title: PackagedPaths
  - id: instance
    resource: src/Voyager/Core/RenderedInstance.php
    title: runningPackaged(), packagedPaths()
  - id: base
    resource: src/Voyager/Core/Concerns/BasePathManagement.php
    title: storagePath(), databasePath()
  - id: caches
    resource: src/Voyager/Core/Concerns/InstanceBootstrapping.php
    title: normalizeCachePath()
  - id: unit
    resource: tests/Core/PackagedPathsTest.php
    title: PackagedPaths Pest coverage
  - id: e2e
    resource: tests/Core/PackagedAppTest.php
    title: phar end-to-end Pest coverage
---

# Signal

`RenderedInstance::packagedPaths()` resolves once: base path starts with `phar://` → `PackagedPaths`; else `Phar::running()`; else null. `runningPackaged()` is the bool.[^instance] `Phar::running()` answers only from code that itself lives in the phar, so a build's framework (inside the phar) sees it, a test's framework on disk does not; the base path is what builds pass.[^e2e]

Name: phar metadata `name`, else the phar's basename.[^paths]

# Data directory

| OS | `dataPath()` |
|---|---|
| Darwin | `~/Library/Application Support/<name>` |
| Linux | `$XDG_DATA_HOME/<name>`, else `~/.local/share/<name>` |
| Windows | `%APPDATA%\<name>` |

[^unit]

# What moves

* `storagePath()` → `<data>/storage` unless `useStoragePath()` or `VENUSIAN_STORAGE_PATH` set it.[^base][^e2e]
* `databasePath()` → `<data>/database` unless `useDatabasePath()` set it.[^base][^e2e]
* `packages.php`, `services.php`, `config.php`, `signals.php` caches → `<data>/bootstrap/cache/` unless their `APP_*_CACHE` env names a path.[^caches][^e2e]
* `bootstrap/providers.php` stays in the phar.[^e2e]

# Seed

First call creates `<data>/bootstrap/cache` and copies `storage/` and `database/` out of the phar. Guard is `is_dir(<data>)`: a second run, or a newer phar, never touches the user's files.[^paths][^unit]

Every call compares the phar's size and modification time with `<data>/bootstrap/cache/phar.stamp`. A different phar (an upgrade, a rebuild) deletes `<data>/bootstrap/cache/*.php` and writes the new stamp, so the packages, services, config and signals caches are rebuilt from the phar now running: a cache listing an old version's providers would otherwise name classes the new phar lacks. Storage and database stay.[^paths][^unit]

[^paths]: PackagedPaths
[^instance]: runningPackaged(), packagedPaths()
[^base]: storagePath(), databasePath()
[^caches]: normalizeCachePath()
[^unit]: PackagedPaths Pest coverage
[^e2e]: phar end-to-end Pest coverage
