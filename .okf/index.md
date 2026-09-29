---
okf_version: "0.2"
---

# Venusian framework

* [Framework](orientation/framework.md) - PHP 8.4 framework package `venusian/framework` at 0.10.0, the `replace` map, and which providers boot.

# IOPools

* [Event loop](io-pools/event-loop.md) - One turn waits once, then fires wakes, deadlines, ticks, follow-ons and mail; `until()` borrows or suspends.
* [Resources and waiters](io-pools/resources.md) - Resource kinds by interface, the one-sleeper succession, background resources, kqueue/epoll/select backends.
* [Loop mail](io-pools/mail.md) - `post()` and pumpables collect mail; the `signal` or `sketch` mail handler receives it.
* [Worker pools](io-pools/worker-pools.md) - `submit(ShouldPool)` on a named pool, process or thread; gigs cross serialized.
* [Async](io-pools/async.md) - `$loop->async()` runs a body in a fiber so `wait()` suspends instead of borrowing.
* [Defer](io-pools/defer.md) - `$loop->defer()` runs a closure on the next turn and settles a promise.

# Signals

* [Queued listeners](signals/queued-listeners.md) - `ShouldQueue` listeners and `QueuedClosure` go on the queue as `CallQueuedListener`; unique listeners lock first.

# Log, cache, Redis

* [Async logging](log/async.md) - `*Async()` twins write through a worker pool, one gig per channel at a time, in call order.
* [Cache stores](cache/stores.md) - Array, database, file, null and redis stores; locks, tags, cache commands.
* [Async cache](cache/async.md) - `Repository::async()` promises; file and database on worker pools, redis on the loop's socket.
* [Redis component](redis/component.md) - Manager and connections for phpredis or predis, booted by default.
* [Redis on the loop](redis/loop.md) - `RedisPipe` promises on a loop socket; `ListPush`; `ListPop` as `RedisMessage` mail.

# Queue, bus, broadcasting

* [Queue](queue/component.md) - sync, database, redis, background, deferred, failover, null drivers; `queue:work`; failed jobs; table migrations.
* [Async queue](queue/async.md) - `$queue->async()` pushes without blocking; call order and after-commit kept.
* [Bus](bus/component.md) - `dispatch()`, chains, database batches, unique jobs, `BatchFake`.
* [Outbound broadcasting](broadcasting/outbound.md) - `ShouldBroadcast` through the queue, `ShouldBroadcastNow` now; redis, pusher/reverb, log, null.

# Concurrency, process, pipeline, workflows

* [Concurrency drivers](concurrency/drivers.md) - process, fork, sync, pool behind `run()`; process and pool also `async()`.
* [Process on the loop](process/async.md) - `waitAsync()` / `runAsync()` wait on a process through the loop.
* [Pipeline](pipeline/component.md) - `app('pipeline')`, `Hub`, `withinTransaction()`.
* [Workflow graph](workflows/graph.md) - Node graph on IOPools promises; the loop runtime; standalone loop with no app.

# Hashing, encryption

* [Hashing](hashing/component.md) - bcrypt, argon2i, argon2id behind `HashManager`; `makeAsync()` / `checkAsync()` on a worker pool.
* [Encryption](encryption/component.md) - AES-CBC with HMAC or AES-GCM behind `app('encrypter')`; previous keys; signs serialized closures.

# Database

* [Database](database/component.md) - Illuminate toolkit. Eloquent is `Instrument`. `app('db')`. No facade.
* [Database on the loop](database/loop.md) - `via()` gigs, `stream()` as `QueryChunk` mail, per-connection reader-writer lanes.
* [Pagination](pagination/component.md) - Three paginators, resolved through the container. No HTTP request.
* [Graph](graph/component.md) - Neo4j driver on the boot list; builder compiles to Cypher; `cypher()` helpers; `via()` inherited.

# Http

* [Http client](http/component.md) - Guzzle client behind `app('http')`. Sync sends block; `async()` answers with a loop promise.
* [Async drivers](http/async-drivers.md) - `curl` holds the loop's sleep; `pcurl` puts curl's sockets and timer in the loop's wait.

# Filesystem

* [Storage](filesystem/storage.md) - `storage()` reaches `Storage`: disks by name, the default disk's calls, `via()`, fakes.
* [Filesystem offloading](filesystem/offloading.md) - `via()` runs calls in pool workers; path lanes order them; `stream()` reads as `FileChunk` mail.

# Console

* [Computer](console/computer.md) - How built-in commands get onto `php computer`'s list, and which are registered.

# Sketches

* [Sketch runtime](sketches/runtime.md) - `Sketch` base, frame timer calling `loop($mail)`, registry, deferred provider.
* [Sketch runner](sketches/runner.md) - Rocket kernel, `php rocket`, mail delivered to the sketch.
