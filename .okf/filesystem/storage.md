---
type: Module
title: Storage
description: storage() reaches the Storage object - disks by name, the default disk's calls, via(), fakes.
resource: src/Voyager/Filesystem/Storage.php
tags: [filesystem, storage, disks]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: storage
    resource: src/Voyager/Filesystem/Storage.php
    title: Storage
  - id: provider
    resource: src/Voyager/Filesystem/FilesystemServiceProvider.php
    title: FilesystemServiceProvider
  - id: config
    resource: config/filesystems.php
    title: filesystems config
---

# Overview

Bindings: `files` (local `Filesystem`), `filesystem` (`FilesystemManager`), `filesystem.disk`, `filesystem.cloud`, `Storage` singleton. `storage()` returns `app(Storage::class)`. No `__callStatic`; `Storage` is an object, not a facade.[^provider][^storage]

`Storage`: `disk($name)`, `cloud()`, `build($config)`, `via($pool)` (the default disk's), `fake($disk, $config)`, `persistentFake()`, `manager()`; other calls go to the default disk.[^storage]

`fake()` swaps the disk for an emptied local disk under `storage/framework/testing/disks/<name>`; `persistentFake()` keeps what earlier runs wrote.[^storage]

Disks from `config/filesystems.php`: default `local`; `local`, `public`, `s3`. Flysystem local is required; s3, ftp and sftp adapters need their packages.[^config]

Offloading: [filesystem offloading](offloading.md).

[^storage]: Storage
[^provider]: FilesystemServiceProvider
[^config]: filesystems config
