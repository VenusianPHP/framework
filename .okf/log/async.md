---
type: Module
title: Async logging
description: Every PSR level has an *Async() twin that writes through a worker pool as WriteLog gigs, one gig per channel at a time, in call order.
resource: src/Voyager/Log/AsyncWrites.php
tags: [log, async, pool]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: writes
    resource: src/Voyager/Log/AsyncWrites.php
    title: AsyncWrites
  - id: gig
    resource: src/Voyager/Log/WriteLog.php
    title: WriteLog
  - id: logger
    resource: src/Voyager/Log/Logger.php
    title: Logger
  - id: manager
    resource: src/Voyager/Log/LogManager.php
    title: LogManager
---

# Overview

`info()` and the other PSR calls block, as ever. `infoAsync()` … `debugAsync()` and `logAsync($level, …)` exist on `Logger` and on `LogManager` (default channel). Each returns a promise settled once a worker has written the line. No `Log` facade.[^logger][^manager]

A level the channel doesn't handle returns an already-settled promise; nothing crosses. The `MessageLogged` signal fires in the caller.[^logger]

# Crossing

`AsyncWrites` builds each call where it is made: level, message, context with exceptions flattened (`FlattenedThrowable`), timestamp, and the log Context snapshot. Proven serializable per call, so one bad call rejects alone with `InvalidArgumentException`. The gig `WriteLog` carries the channel's config (a stack's members' too); the worker builds the channel from it, taps and processors included.[^writes][^gig]

One gig out per channel. Calls made while it is out batch into the next gig, so lines land in call order however many workers there are.[^writes]

Pool: `thread-workers` when bound, else `process-workers`, else `InvalidArgumentException`.[^writes]

# Stop

`AsyncWrites` registers `onStop()`: calls still queued are written in-process, blocking, in order. A gig already out belongs to the pool, whose `shutDown()` settles it.[^writes]

[^writes]: AsyncWrites
[^gig]: WriteLog
[^logger]: Logger
[^manager]: LogManager
