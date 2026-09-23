---
okf_version: "0.2"
---

# Venusian framework

* [Framework](orientation/framework.md) - PHP 8.4 framework package `venusian/framework` at 0.9.0, and which providers actually boot.

# IOPools

* [Event loop](io-pools/event-loop.md) - The loop that waits on timers, streams, and tickables, and how `until()` borrows it.
* [Worker pools](io-pools/worker-pools.md) - One `submit(): Promise` API with a process driver and a ZTS thread driver.
* [Work targets](io-pools/work-targets.md) - `run(ShouldPool): Promise` with five homes; `via()` sends a disk call to one of them.
* [Async](io-pools/async.md) - `$loop->async()` runs a body in a fiber so `wait()` suspends instead of borrowing.
* [Defer](io-pools/defer.md) - `$loop->defer()` runs a closure on the next turn and settles a promise.

# Log, cache, Redis

* [Deferred log channel](log/deferred-channel.md) - A log channel that buffers records and flushes once per loop turn.
* [Cache stores](cache/stores.md) - Array, file, null, and Redis stores, plus `cache:clear` and `cache:forget`.
* [Cache defer](cache/defer.md) - `Cache::defer()` queues every call on the loop.
* [Redis component](redis/component.md) - Connections, manager, and the provider that boots Redis.
* [Redis push/pop](redis/push-pop.md) - A loop resource that pushes events and pops them back as mail.

# Broadcasting

* [Outbound broadcasting](broadcasting/outbound.md) - Send-only; queue wraps the send; receive is a loop resource.

# Concurrency

* [Concurrency drivers](concurrency/drivers.md) - sync, process and fork behind one `run()`/`defer()`, and the one-line closure trap.

# Workflows

* [Workflow graph](workflows/graph.md) - PocketFlow node graph from 0.8; awaitables are IOPools promises; a bare node gets an isolated loop.

# Hashing

* [Hashing](hashing/component.md) - bcrypt, argon2i, and argon2id behind `HashManager`. `HashGig` runs `make()` on a pool worker.

# Database

* [Database](database/component.md) - Illuminate toolkit. Eloquent is `Instrument`. `app('db')`. No facade.
* [Database on the loop](database/loop.md) - via() promises, stream() mail, builders serialize by connection name.
* [Pagination](pagination/component.md) - Three paginators, resolved through the container. No HTTP request.
* [Graph](graph/component.md) - Opt-in Neo4j provider. `cypher()` autoloads. `via()` inherited.

# Http

* [Http client](http/component.md) - Guzzle client behind `app('http')`. Sync sends stay blocking. No facade.
* [Async drivers](http/async-drivers.md) - `curl` and `pcurl` loop handlers behind one Guzzle promise bridge.

# Filesystem

* [Storage facade](filesystem/storage.md) - `storage()` reaches a `Storage` object: disks, `via()`, fakes, `stream()`.
* [File stream as mail](filesystem/file-stream.md) - Pool workers read ranges; bytes arrive as `FileChunk` mail in offset order.

# Console

* [Computer](console/computer.md) - How built-in commands get onto `php computer`'s available list.

# Sketches

* [Sketch runtime](sketches/runtime.md) - `Sketch` base, one-shot runner on the loop, registry, and the deferred provider.
* [Sketch runner](sketches/runner.md) - Rocket kernel, `php rocket`, re-armed timer, what 0.9 dropped.
