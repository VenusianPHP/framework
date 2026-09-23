---
type: Module
title: Redis push/pop
description: A loop resource that pushes events and pops them back as mail.
resource: src/Voyager/Redis/RedisQueueResource.php
tags: [redis, iopools]
status: draft
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

phpredis does not expose its socket, so the polling resource calls `LPOP` on each tick. Predis can join `select`: the watched resource blocks in `BLPOP` on a dedicated connection, because that call occupies the connection it runs on.[^resource]

[^resource]: RedisQueueResource
