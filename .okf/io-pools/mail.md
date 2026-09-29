---
type: Module
title: Loop mail
description: post() and Pumpable resources collect mail; a non-quiet turn hands it to the mail handler driver, signal or sketch.
resource: src/Voyager/IOPools/MailHandlers/MailHandlerManager.php
tags: [iopools, loop, mail, signals]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/IOPools/MailHandlers/MailHandlerManager.php
    title: MailHandlerManager
  - id: signal
    resource: src/Voyager/IOPools/MailHandlers/SignalMailHandler.php
    title: SignalMailHandler
  - id: sketch
    resource: src/Voyager/IOPools/MailHandlers/SketchMailHandler.php
    title: SketchMailHandler
  - id: deliver
    resource: src/Voyager/Core/Sketches/DeliverMailToSketches.php
    title: DeliverMailToSketches
---

# Overview

Mail is any object. `$loop->post($mail)` queues it; a `Pumpable` resource returns it from `pump()`. Collected oldest first. Handed off once per non-quiet turn: `run()` turns hand off, `until()` / `wait()` turns do not.[^manager]

Driver from `io-pools.event_loop.mail_handlers.default`.[^manager]

| Driver | Does |
|---|---|
| `signal` | dispatches each piece through `signals`, in arrival order. A `NamedSignal` dispatches under its `name()`. |
| `sketch` | holds mail; `take()` empties it. The sketch runner hands it to `loop($mail)` each frame. |

A rocket run sets the key to `sketch` in `DeliverMailToSketches`, right after config loads, before anything builds the loop.[^deliver]

Components that deliver mail: Database `stream()` (`QueryChunk`), Filesystem `stream()` (`FileChunk`), Redis `ListPop` (`RedisMessage`), Concurrency `async()` (`ConcurrencyResults`).

[^manager]: MailHandlerManager
[^signal]: SignalMailHandler
[^sketch]: SketchMailHandler
[^deliver]: DeliverMailToSketches
