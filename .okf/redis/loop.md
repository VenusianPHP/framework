---
type: Module
title: Redis on the loop
description: RedisPipe sends commands on a loop socket and answers with promises; ListPush pushes, ListPop pops a list as RedisMessage mail.
resource: src/Voyager/Redis/Sockets/RedisPipe.php
tags: [redis, loop, resp, lists]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: pipe
    resource: src/Voyager/Redis/Sockets/RedisPipe.php
    title: RedisPipe
  - id: endpoint
    resource: src/Voyager/Redis/Sockets/RedisEndpoint.php
    title: RedisEndpoint
  - id: push
    resource: src/Voyager/Redis/Lists/ListPush.php
    title: ListPush
  - id: pop
    resource: src/Voyager/Redis/Lists/ListPop.php
    title: ListPop
  - id: message
    resource: src/Voyager/Redis/RedisMessage.php
    title: RedisMessage
  - id: manager
    resource: src/Voyager/Redis/RedisManager.php
    title: RedisManager
---

# Overview

The loop speaks RESP on sockets of its own (`RedisSocket`). `RedisEndpoint` reads host, port, auth, database and prefix from the same connection config the connectors use, so a socket and a `Connection` of one name agree on every key.[^endpoint]

# Pipe

`app('redis')->pipe($connection)`: a new `RedisPipe`. `send(list<string> $arguments, string $describe): Promise`. Connects on first send. Replies in send order: string, int, list or null; an error reply rejects with `RedisSocketException` naming `$describe`. On the loop only while a reply is due, so an idle pipe never keeps `run()` alive.[^pipe][^manager]

Keys go as given: prefix them with `prefix()`. Values go as raw bytes; the connection's serializer and compression don't apply.[^pipe]

Users: cache `RedisAsyncStore`, queue `RedisQueue` async pushes.

# Lists

`listPush($key, $connection)`: `ListPush::push(string ...$values): Promise`, `RPUSH` through its own pipe.[^push]

`listPop($key, $connection, $batch = 64)`: `ListPop`, register with `$loop->resource($name, $pop)`. A `BLPOP` always waits on its own socket, which is in the loop's wait, so the loop wakes when a value lands. Each pop pipelines an `LPOP` of up to `$batch` more, then the next `BLPOP`. `pump()` returns `RedisMessage` mail: `key`, `raw` bytes, name `redis:{key}`.[^pop][^message]

A popped value is gone from Redis, so `release()` unblocks the waiting `BLPOP` from a second connection, reads replies in flight, and pushes popped-not-pumped values back to the head of the list in order. Runs at loop stop, at process exit, or on call after `forget()`.[^pop]

[^pipe]: RedisPipe
[^endpoint]: RedisEndpoint
[^push]: ListPush
[^pop]: ListPop
[^message]: RedisMessage
[^manager]: RedisManager
