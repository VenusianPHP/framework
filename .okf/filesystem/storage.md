---
type: Module
title: Storage facade
description: An object with methods over disks, via(), fakes, and stream(); reached through storage(), not a static proxy.
resource: src/Voyager/Filesystem/Storage.php
tags: [filesystem, storage, facade]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: cursor-grok-4.6, at: 2026-09-22T17:50:00Z }
sources:
  - id: storage
    resource: src/Voyager/Filesystem/Storage.php
    title: Storage
  - id: helper
    resource: src/Voyager/Filesystem/helpers.php
    title: storage() helper
  - id: provider
    resource: src/Voyager/Filesystem/FilesystemServiceProvider.php
    title: FilesystemServiceProvider
---

# Overview

`Storage` is a facade in the pattern sense: a final class with typed methods over `FilesystemManager` and work targets. `app(Storage::class)` is the singleton; `storage()` is the helper. No `__callStatic`, no `MagicAlias`. Swap it by binding another instance.[^storage][^helper]

`FilesystemServiceProvider` binds the singleton from `app('filesystem')` and `app('work-targets')`. The factory is lazy, so `work-targets` can be registered later (`IOPoolsServiceProvider` boots after Filesystem).[^provider]

`fake()` / `persistentFake()` swap a named disk for a local root under `app()->storagePath('framework/testing/disks/<name>')`. `build()` makes an on-demand disk with no name; `via()` on one throws `LogicException`.

`via($target, $disk)` returns `OffloadedDisk`. `stream($path, $disk, $chunk)` returns a [`FileStreamResource`](file-stream.md).

[^storage]: Storage
[^helper]: storage() helper
[^provider]: FilesystemServiceProvider
