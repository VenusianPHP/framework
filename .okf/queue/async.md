---
type: Module
title: Async queue
description: $queue->async() pushes without blocking - redis on the connection's loop socket, database as a gig through the connection's lanes; call order and after-commit kept.
resource: src/Voyager/Queue/Async/AsyncQueue.php
tags: [queue, async, redis, database]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: async
    resource: src/Voyager/Queue/Async/AsyncQueue.php
    title: AsyncQueue
  - id: contract
    resource: src/Voyager/Queue/Async/PushesAsync.php
    title: PushesAsync
  - id: queue
    resource: src/Voyager/Queue/Queue.php
    title: Queue::enqueueUsingAsync
  - id: redis
    resource: src/Voyager/Queue/RedisQueue.php
    title: RedisQueue
  - id: database
    resource: src/Voyager/Queue/DatabaseQueue.php
    title: DatabaseQueue
  - id: insert
    resource: src/Voyager/Queue/Async/InsertJobRecord.php
    title: InsertJobRecord
---

# Overview

`$queue->async()` returns `AsyncQueue`: `push`, `pushOn`, `later`, `laterOn`, `pushRaw`, each a promise of the job's id.[^async]

A queue implementing `PushesAsync` does its own I/O without blocking: `redis`, `database`. Any other runs its push here; a push that already returns a promise (`background`, `deferred`) hands that promise back; a throw rejects.[^async][^contract]

The payload is built when the call is made. `JobQueueing` fires before the send, `JobQueued` with the id after, both in this process. A job marked after-commit inside a transaction is held until the commit; a rollback rejects its promise (`RuntimeException`) and releases a unique job's lock.[^queue]

# redis

The push script runs on the connection's `RedisPipe`, the loop's own socket; keys get the connection prefix, as the blocking client adds it to `EVAL`'s keys. `later()` writes the delayed set with its time from the call. Blocking `pushRaw`, `later`, `pop`, `clear` on the same queue object wait for the async pushes before them, so the list keeps call order.[^redis]

# database

The row, `available_at` included, is built here; `InsertJobRecord` inserts it in a worker, offloaded like any write on the connection ([Database on the loop](/database/loop.md)). Blocking queries on the connection wait for it. Inside an open transaction on that connection the push rejects with `LogicException`.[^database][^insert]

[^async]: AsyncQueue
[^contract]: PushesAsync
[^queue]: Queue::enqueueUsingAsync
[^redis]: RedisQueue
[^database]: DatabaseQueue
[^insert]: InsertJobRecord
