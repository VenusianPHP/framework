---
type: Module
title: Redis push/pop
description: A loop resource that pushes events and pops them back as mail.
resource: src/Voyager/Redis/RedisQueueResource.php
tags: [redis, iopools]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: resource
    resource: src/Voyager/Redis/RedisQueueResource.php
    title: RedisQueueResource
---

# Overview

`RedisQueueResource::make()` picks the concrete class from the connection. A `PredisConnection` gets `WatchedRedisQueueResource`. Anything else gets `PollingRedisQueueResource`.[^resource]

`post(Event)` pushes JSON `{"class": Event class, "data": event->toData()}` with `RPUSH`. `pump()` returns the events popped since the last pump. A payload that does not reconstitute becomes a `RedisMessage` holding the key and the raw string.[^resource]

# How it waits

phpredis does not expose its socket, so the polling resource calls `LPOP` on each tick. Predis can join `select`: the watched resource arms `BLPOP` on the connection you pass in. That call occupies that connection; the resource does not open a second one.[^resource]

[^resource]: RedisQueueResource
