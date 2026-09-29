---
type: Module
title: Redis component
description: RedisManager and connections for phpredis or predis, booted by default; loop pipes and list push/pop live beside them.
resource: src/Voyager/Redis/RedisManager.php
tags: [redis, component]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/Redis/RedisManager.php
    title: RedisManager
  - id: config
    resource: config/database.php
    title: database.redis config
---

# Overview

`RedisServiceProvider` is on `DefaultProviders`. `app('redis')` is `RedisManager`; connections come from `database.redis`. Client `database.redis.client`, default `phpredis` (`PhpRedisConnector`, ext-redis); `predis` uses `PredisConnector` and needs `predis/predis`, which this package does not require.[^manager][^config]

Command signals: `Voyager\Redis\Signals\CommandExecuted`, `CommandFailed`. Limiters: `ConcurrencyLimiter`, `DurationLimiter` and builders.

Connection calls block. Loop-side Redis is [Redis on the loop](loop.md): its own sockets, so the connection's client never blocks the loop.

[^manager]: RedisManager
[^config]: database.redis config
