---
type: Module
title: Filesystem offloading
description: via() on a disk or the local filesystem runs each call in a pool worker; path lanes order calls per path; stream() reads a file as FileChunk mail.
resource: src/Voyager/Filesystem/Offloading/Offloader.php
tags: [filesystem, iopools, via, stream, lanes]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: disk
    resource: src/Voyager/Filesystem/OffloadedDisk.php
    title: OffloadedDisk
  - id: files
    resource: src/Voyager/Filesystem/OffloadedFiles.php
    title: OffloadedFiles
  - id: offloader
    resource: src/Voyager/Filesystem/Offloading/Offloader.php
    title: Offloader
  - id: lanes
    resource: src/Voyager/Filesystem/Offloading/PathLanes.php
    title: PathLanes
  - id: operator
    resource: src/Voyager/Filesystem/Offloading/SettlingOperator.php
    title: SettlingOperator
  - id: chunk
    resource: src/Voyager/Filesystem/FileChunk.php
    title: FileChunk
---

# Overview

`$disk->via($pool)` returns `OffloadedDisk`; `app('files')->via($pool)` returns `OffloadedFiles`. Each method is the blocking method of the same name as a `FilesystemCall` gig: the promise settles with its return value or rejects with the worker's `RemoteException`. The worker builds the disk from the caller's disk config.[^disk][^files]

Only data crosses: arguments must be strings, numbers, booleans, null, or arrays of them, checked when the call is made. Contents cross as strings. `readStream()` / `writeStream()` are absent (a resource can't leave its process); `putFile()` has the worker open the file itself. `path()`, `url()` and temporary URLs touch no storage, so they aren't offloaded. On the local filesystem `name()`, `basename()`, `dirname()`, `extension()`, `lines()`, `getRequire()`, `requireOnce()` stay blocking.[^offloader][^disk][^files]

Pool rule: `thread-workers` when bound, else `process-workers`.

# Order

`PathLanes` orders calls by the paths they touch: a call starts once no earlier call holds any of its paths; a path holds everything under it; unrelated paths run side by side.[^lanes]

Blocking calls wait their turn. A disk offloaded once gets a `SettlingOperator` wrapping its Flysystem operator, so every blocking disk method waits for the offloaded calls on its paths. The local `Filesystem` settles its own paths before each blocking call.[^operator]

# stream()

`stream($path, $chunk_bytes = 1048576): Promise` reads the file a chunk at a time, each a `FileChunk` as loop mail: `source`, `path`, `offset`, `bytes`, `last`; named `disk:{disk}:{path}` or `file:{path}`. Oldest first, last marked. The promise resolves with the bytes read. `run()` hands the mail to the mail handler; `until()` turns don't.[^chunk][^offloader]

[^disk]: OffloadedDisk
[^files]: OffloadedFiles
[^offloader]: Offloader
[^lanes]: PathLanes
[^operator]: SettlingOperator
[^chunk]: FileChunk
