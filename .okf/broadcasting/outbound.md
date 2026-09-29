---
type: Module
title: Outbound broadcasting
description: Send-only broadcasting - ShouldBroadcast through the queue, ShouldBroadcastNow now; redis, pusher/reverb, log, null drivers.
resource: src/Voyager/Broadcasting/BroadcastManager.php
tags: [broadcasting, queue, redis, pusher]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/Broadcasting/BroadcastManager.php
    title: BroadcastManager
  - id: event
    resource: src/Voyager/Broadcasting/BroadcastEvent.php
    title: BroadcastEvent
  - id: provider
    resource: src/Voyager/Broadcasting/BroadcastServiceProvider.php
    title: BroadcastServiceProvider
  - id: redis
    resource: src/Voyager/Broadcasting/Broadcasters/RedisBroadcaster.php
    title: RedisBroadcaster
  - id: signals
    resource: src/Voyager/Signals/SignalDispatcher.php
    title: SignalDispatcher ShouldBroadcast hook
  - id: config
    resource: config/broadcasting.php
    title: broadcasting config
---

# Overview

Outbound only. `app('broadcast')` is `BroadcastManager` (`Broadcasting\Factory`); `app('broadcast.connection')` the default broadcaster. Helpers `broadcast()`, `broadcast_if()` return `PendingBroadcast`, which dispatches through `signals` in its destructor. No facade, no channel auth routes.[^provider][^manager]

A dispatched signal implementing `ShouldBroadcast`, with `broadcastWhen()` true or absent, goes to `BroadcastManager::queue()`.[^signals]

`ShouldBroadcastNow` (or `shouldBroadcastNow()` true) runs `BroadcastEvent` now through the bus. Otherwise `BroadcastEvent` is pushed on the event's `connection` and `broadcastQueue` / `queue`; `ShouldBeUnique` takes a lock first; `ShouldRescue` reports instead of throwing.[^manager]

`BroadcastEvent`: name `broadcastAs()` or the class; channels `broadcastOn()`, per connection via `broadcastConnections()`; payload `broadcastWith()` (plus `socket`) or the public properties. Copies the event's `tries`, `timeout`, `backoff`, `afterCommit`, `maxExceptions`.[^event]

# Drivers

Default `null` (`BROADCAST_CONNECTION`).[^config]

| Driver | Sends |
|---|---|
| redis | `PUBLISH` of `{event, data, socket}` per channel, channel prefixed with `database.redis.options.prefix`; blocking connection |
| pusher, reverb | Pusher SDK over Guzzle; needs `pusher/pusher-php-server`, otherwise `RuntimeException` naming it |
| log | one `info` line: event, channels, JSON payload |
| null | nothing |

`toOthers($socket)` / `dontBroadcastToCurrentUser($socket)` take the socket id as an argument. Ably is not supported.

Receiving (SUBSCRIBE) is not this module; `ListPop` in [Redis on the loop](/redis/loop.md) is the loop-side list reader.

[^manager]: BroadcastManager
[^event]: BroadcastEvent
[^provider]: BroadcastServiceProvider
[^redis]: RedisBroadcaster
[^signals]: SignalDispatcher ShouldBroadcast hook
[^config]: broadcasting config
