---
type: Module
title: File stream as mail
description: A file read in chunks by pool workers; each slice arrives as a FileChunk event, in offset order.
resource: src/Voyager/Filesystem/FileStreamResource.php
tags: [filesystem, stream, iopools, mail]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: cursor-grok-4.6, at: 2026-09-22T17:50:00Z }
sources:
  - id: resource
    resource: src/Voyager/Filesystem/FileStreamResource.php
    title: FileStreamResource
  - id: chunk
    resource: src/Voyager/Filesystem/FileChunk.php
    title: FileChunk
  - id: adapter
    resource: src/Voyager/Filesystem/FilesystemAdapter.php
    title: FilesystemAdapter::readRange
---

# Overview

`FileStreamResource` is a loop `Tickable`/`Pumpable` named `stream:<disk>:<path>`. It asks the pool for `size()` once, then `DiskGig(..., 'readRange', ...)` per chunk (default `1 << 20`, two in flight). Each landed slice is a `FileChunk` (`disk`, `path`, `offset`, `bytes`, `last`). It forgets itself when the last chunk is queued, so it never holds `run()` open.[^resource][^chunk]

`readRange` is blocking, on both the native `Filesystem` and `FilesystemAdapter`. A worker calls it so the caller isn't blocked.[^adapter]

# Last chunk vs done()

The last `FileChunk` is queued in the same call that resolves `done()`. `forget()` on a `Pumpable` also pumps remaining mail into the notebook bag. `pump()` on a later turn is then empty. `until()`/`await()` turns are quiet, so a `done()->then()` fires before the mail handler sees that chunk. Consumers that need the bytes listen for `last === true`, not the promise. The promise is the byte count and the failure. Drive a loud `run()` if the handler must see the mail.

[^resource]: FileStreamResource
[^chunk]: FileChunk
[^adapter]: FilesystemAdapter::readRange
