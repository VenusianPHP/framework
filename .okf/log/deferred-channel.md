---
type: Module
title: Deferred log channel
description: A log channel that buffers records and flushes once per loop turn.
resource: src/Voyager/Log/LogManager.php
tags: [log, defer]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: manager
    resource: src/Voyager/Log/LogManager.php
    title: LogManager::createDeferredDriver
  - id: flush
    resource: src/Voyager/Log/DeferredFlush.php
    title: DeferredFlush
---

# Overview

`LogManager::info()` and the other PSR methods stay synchronous. They call the default channel. The `deferred` driver is a separate channel. There is no `Log` facade.[^manager]

# Driver

Config shape: `driver: deferred`, `channel` naming the inner channel, `limit` defaulting to `0`.

`createDeferredDriver()` wraps each handler of the inner Monolog logger in a `BufferHandler`. An arming handler calls `DeferredFlush::arm()` on the first record and returns false so the buffers still see the record. `DeferredFlush` uses `Loop::defer()` for one flush per turn. There is no `DeferredFlush::stop()`; the constructor registers `$loop->onStop()` so a shutdown flush still runs.[^manager][^flush]

The container key on the logger is `signals`, not `events`.[^manager]

[^manager]: LogManager::createDeferredDriver
[^flush]: DeferredFlush
