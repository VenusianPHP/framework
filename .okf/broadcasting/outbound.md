---
type: Module
title: Outbound broadcasting
description: Send-only BroadcastManager. Queue wraps the send. Socket id is explicit. Receive is not this module.
resource: src/Voyager/Broadcasting
tags: [broadcasting, queue, redis, pusher]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: cursor-grok-4.6, at: 2026-09-22T18:45:00Z }
sources:
  - id: manager
    resource: src/Voyager/Broadcasting/BroadcastManager.php
    title: BroadcastManager
  - id: provider
    resource: src/Voyager/Broadcasting/BroadcastServiceProvider.php
    title: BroadcastServiceProvider
  - id: sockets
    resource: src/Voyager/Broadcasting/InteractsWithSockets.php
    title: InteractsWithSockets
  - id: signals
    resource: src/Voyager/Signals/SignalDispatcher.php
    title: SignalDispatcher ShouldBroadcast hook
  - id: redis-mail
    resource: redis/push-pop.md
    title: Redis push/pop
---

# Overview

Outbound only. `broadcast($event)` returns `PendingBroadcast`; `__destruct` dispatches through `signals`. A `ShouldBroadcast` payload is handed to `BroadcastManager::queue()`. `ShouldBroadcastNow` is `Bus::dispatchNow`; everything else is `queue->connection($event->connection)->pushOn($broadcastQueue, BroadcastEvent)`.[^manager][^signals]

`app('broadcast')` is `BroadcastManager`. `app('broadcast.connection')` is the default driver. Helpers: `broadcast()` / `broadcast_if`. No Broadcast facade.[^provider]

# Drivers

redis (this package's Redis component), pusher and reverb (same driver, different config; `pusher/pusher-php-server` suggested — a named connection without the class throws `RuntimeException`), log, null. Default is `null`. Ably is cut.

# Sockets

`dontBroadcastToCurrentUser(string $socket)` / `PendingBroadcast::toOthers(string $socket)`. No-arg is `ArgumentCountError`. Pusher/Reverb-only exclusion; Redis only passes the field through.[^sockets]

# Out of scope

SUBSCRIBE / device receive is not this module. That is a loop resource, sibling of [Redis push/pop](/redis/push-pop.md). No `auth()`, BroadcastController, channel routes, or `BroadcastManager::socket()`.[^redis-mail]

[^manager]: BroadcastManager
[^provider]: BroadcastServiceProvider
[^sockets]: InteractsWithSockets
[^signals]: SignalDispatcher ShouldBroadcast hook
[^redis-mail]: Redis push/pop
