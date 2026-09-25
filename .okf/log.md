# Update Log

## 2026-09-25

* **Update**: [Worker pools](io-pools/worker-pools.md) — frames open with `VFP\x01`; process workers say hello after boot and the parent holds the first gig for it; `hello_timeout_s` bounds the wait on both drivers (config `io-pools.thread_pool.hello_timeout_s`, env `POOL_HELLO_TIMEOUT`). Back to `draft` pending re-verify.

## 2026-09-23

* **Update**: [Worker pools](io-pools/worker-pools.md) — clean CI checkouts had no `bootstrap/cache`, so process workers died during `PackageManifest::write()`. The exception went to stdout (`HandleExceptions` → `ConsoleOutput`), so `DeadWorkerException` quoted an empty stderr tail. `tests/Pest.php` mkdir's `bootstrap/cache` and `storage/app` at test time (app-skeleton paths, not shipped). `pool-worker` writes a bootstrap failure to stderr.

* **Update**: [Async drivers](http/async-drivers.md) — adopt unwraps `FluentPromise`. [Event loop](io-pools/event-loop.md) — `until()` flushes before giving up; `then()` second callable. Both back to `draft` pending re-verify.

* **Update**: [Event loop](io-pools/event-loop.md) — `then()` takes an optional second callable so a foreign `then($resolve, $reject)` reject handler reaches the chain. Back to `draft` pending re-verify.
* **Update**: [Async drivers](http/async-drivers.md) — adopt unwraps `FluentPromise`. [Event loop](io-pools/event-loop.md) — `until()` flushes before giving up. Both back to `draft` pending re-verify.
* **Audit**: Whole `.okf` tree checked against `0.9.x` tip `5fb34e76550303f5c6cfeb757c63576b5e84bb94`. Every concept is `status: stable` with `verification_key: agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94`.
* **Correction**: [Framework](orientation/framework.md) — boot table reordered to match `DefaultProviders`. Root `replace` map listed. Migration row includes `make:migration`. `neo4j_connection()` throw path clarified.
* **Correction**: [Computer](console/computer.md) — `make:job` is not commented because the queue provider is missing; `QueueServiceProvider` boots and `queue:*` is registered. Loader maps `|` in `AsCommand` name, not the aliases list.
* **Correction**: [Workflow graph](workflows/graph.md) — parallel-batch fans through `LoopRuntime::all()`, not `WorkerPool::submit()`. `all()` rejects the promise; `await()` throws.
* **Correction**: [Cache stores](cache/stores.md) — no `Cache` facade. Tags are array/null/redis only. `cache:clear` / `cache:forget` exist as classes and are not registered.
* **Correction**: [Cache defer](cache/defer.md) — `Repository::defer()`; listed methods only; no `__call`.
* **Correction**: [Deferred log channel](log/deferred-channel.md) — no `DeferredFlush::stop()`; shutdown flush is `$loop->onStop()`.
* **Correction**: [Redis push/pop](redis/push-pop.md) — watched `BLPOP` occupies the connection you pass in; it does not open a second one.
* **Correction**: [Async](io-pools/async.md) — wrapping a `Task` as a foreign thenable throws `ArgumentCountError` in `adopt()`, it does not spin `until()`.
* **Correction**: [Event loop](io-pools/event-loop.md), [Worker pools](io-pools/worker-pools.md), [Work targets](io-pools/work-targets.md), [Http async drivers](http/async-drivers.md), [Http client](http/component.md), [Database](database/component.md), [Database on the loop](database/loop.md), [Graph](graph/component.md), [Concurrency drivers](concurrency/drivers.md), [Sketch runner](sketches/runner.md) — claims tightened to the code.

## 2026-09-22

* **Addition**: [Database on the loop](database/loop.md) — `via()` promises of the blocking result. Builders serialize by connection name. `stream()` is keyed `ModelChunk` mail, one page in flight. `retrieved` fires on the caller. `Connection::via()` allow-lists raw statements.
* **Update**: [Database](database/component.md) — the "not on the loop" line now points at `loop.md`.
* **Update**: [Graph](graph/component.md) — `via()` is inherited from `Connection`. The worker needs the provider.
* **Update**: [Work targets](io-pools/work-targets.md) — `via()` names query builders and connections.
* **Update**: [Framework](orientation/framework.md) — `DatabaseServiceProvider` row notes `via()` / `stream()` on the loop.

* **Addition**: [Sketch runner](sketches/runner.md) — `boot` / `loop` / `shutdown` on a re-armed one-shot timer. `Core\Sketches\Kernel` is Rocket (`ComputerConsoleInstance`). `php rocket` calls `handleSketch()`. `php computer` stays the console kernel. `ROCKET_BINARY` is `'launch'`. 0.9 dropped middleware, run context, `pcntl` in the runner, and mail hand-off into a sketch.
* **Update**: [Framework](orientation/framework.md) — `SketchesServiceProvider` row names the deferred registry and `app('sketches.runner')`. [Sketch runtime](sketches/runtime.md) points at the kernel.

* **Addition**: [Sketch runtime](sketches/runtime.md) — `Sketch` base and `SketchRunner` re-arm a one-shot timer from live `sketches.refresh_rate` (floor 1 Hz). `STOP` stops the loop at 0. Shutdown is once, via `onStop` and a `finally`. Registry names come from the attribute, else kebab basename; duplicates throw. `DiscoverSketches::within()` is class-from-path. Deferred `SketchesServiceProvider` is on the boot list.
* **Update**: [Framework](orientation/framework.md) — `SketchesServiceProvider` boots. Nothing in `DefaultProviders` stays commented out.

* **Addition**: [Database](database/component.md) — Illuminate toolkit. Eloquent is `Instrument`. Bindings behind `app('db')`. Dispatcher is `SignalDispatcher`. `withoutEvents()` wraps `NullDispatcher`. No facade. Suite is sqlite only. Facade-bound tests stay in `tests/Database/deferred/`. Not on the loop.
* **Addition**: [Pagination](pagination/component.md) — `Paginator`, `LengthAwarePaginator`, `CursorPaginator`. `BuildsQueries` resolves them through `ControlPanel::make()`. Page resolvers are static hooks. No HTTP request. Not on the loop.
* **Addition**: [Graph](graph/component.md) — opt-in `GraphServiceProvider`. `database.connections.neo4j`. Helpers `cypher` / `cypher_one` / `cypher_run` / `neo4j_connection`. `make:graph-model`. Not on the loop.
* **Update**: [Framework](orientation/framework.md) — `DatabaseServiceProvider` and deferred `MigrationServiceProvider` are on the boot list. Graph stays opt-in.
* **Update**: Database source is on 0.9 names. Facades in that tree go through `app('db')`, `app('hash')`, `app('files')`, `app(Encrypter::class)`, `config()`, or `Carbon`. The container binding is `signals`, not `events`. `Model::broadcastChannel()` needs `: string` as well as `broadcastChannelRoute()`. `Macroable` and `Conditionable` still declare `Voyager\NutsAndBolts\Concerns` (the files live under `src/Voyager/Macroable` and `src/Voyager/Conditionable`; Http keeps that import). `Voyager\Signals\NullDispatcher` forwards `listen` / `forget` and swallows `dispatch`; `flush` and `push` are no-ops. `BinaryCodec` and `MathException` are copied from 0.8 with no rename. Pagination, Graph, and the Database tests are still ahead.

* **Addition**: [Http client](http/component.md) — `app('http')` / `app('http.async')`. No facade. Sync sends stay the 0.8 blocking path. `HttpServiceProvider` is deferred. `Dispatcher` and `ForwardsCalls` are shims.
* **Addition**: [Async drivers](http/async-drivers.md) — `curl` owns the sleeper slot; `forget()` does not restore a displaced sleeper. `pcurl` ticks on stream fire or curl's timer. libcurl values stay in the `Pcurl*` enums. ext-pcurl needs ext-curl first.
* **Update**: [Framework](orientation/framework.md), [Event loop](io-pools/event-loop.md) — `HttpServiceProvider` is on the boot list. `adopt()` wraps a foreign thenable and `wait()` borrows the loop.

* **Addition**: [Outbound broadcasting](broadcasting/outbound.md) — send path only. redis / pusher+reverb / log / null. Queue wraps the send. Socket id is an argument. SUBSCRIBE / device receive is a loop resource, not this module.
* **Update**: [Framework](orientation/framework.md) — `BroadcastServiceProvider` boots after Redis.

* **Update**: [Framework](orientation/framework.md) — `BusServiceProvider` and `PipelineServiceProvider` were on the boot list but missing from the table. Sketches has no `Sketch` base class yet; the skeleton's sketches reference one.

* **Addition**: [Storage facade](filesystem/storage.md) — `final class Storage` bound as a singleton; `storage()` reaches it. No `__callStatic`. `fake()` roots under `app()->storagePath('framework/testing/disks/<name>')`.
* **Addition**: [File stream as mail](filesystem/file-stream.md) — `FileStreamResource` submits `readRange` gigs, pumps `FileChunk` in offset order, forgets itself on the last chunk. `until()`/`await()` do not hand mail off; a loud `run()` does.
* **Update**: [Work targets](io-pools/work-targets.md), [Framework](orientation/framework.md) — `storage()->via()` / `storage()->stream()` sit on the same work-target and pool path. `FilesystemServiceProvider` also binds `Storage`.

* **Addition**: [Work targets](io-pools/work-targets.md) — `WorkTarget::run(ShouldPool): Promise` with five homes. `via()` returns a proxy; gigs carry disk name + method + args. `ConcurrencyManager->driver()` is the concurrency home because that provider does not bind `ConcurrencyDriver`.
* **Update**: [Framework](orientation/framework.md) — `IOPoolsServiceProvider` also binds `app('work-targets')`.

* **Update**: [Framework](orientation/framework.md) — Flysystem layer from 0.8 is in. `FilesystemServiceProvider` boots. Local, path-prefixing, and read-only are required; s3/ftp/sftp stay suggested. `app('files')` stays the native `Filesystem` bound by Core. `storage_path()` is not a 0.9 helper: disk roots use `app()->storagePath()`. `ControlPanel` has `isBound()`, not `bound()`.
* **Update**: [Worker pools](io-pools/worker-pools.md), [Hashing](hashing/component.md) — a pool worker now runs the console kernel's bootstrappers. `VenusianVoyager::launch()` alone only builds the container, so no gig could resolve a provider binding before this. A worker's `base_path` needs a writable `bootstrap/cache`.
* **Update**: [Workflow graph](workflows/graph.md) — a parallel-batch node fans out over `WorkerPool::submit()`; the pool and `LoopRuntime` must share a loop.
* **Update**: [Async](io-pools/async.md) — `Loop::await()` matches the `Promise` contract, so a `Task` is waited on directly instead of wrapped as a foreign thenable.
* **Update**: [Workflow graph](workflows/graph.md) — node, flow, and batch tests pin `LoopRuntime`. `WorkflowRuntimeException` extends `Voyager\Contracts\Core\VenusianFrameworkException`.
* **Addition**: [Hashing](hashing/component.md) — bcrypt, argon2i, and argon2id behind `HashManager`, wired as `hash`. `HashGig` runs `make()` on a pool worker. `make()` / `check()` stay synchronous. No facade.
* **Update**: [Framework](orientation/framework.md) — `HashServiceProvider` is on the boot list.
* **Update**: [Workflow graph](workflows/graph.md), [Framework](orientation/framework.md) — `AsyncRuntimeManager` drivers are `loop` and `isolated`. `WorkflowsServiceProvider` is deferred and listed in `DefaultProviders`.
* **Update**: [Workflow graph](workflows/graph.md) — `LoopRuntime` implements `AsyncRuntime` over a `Loop`. `await()` borrows on the main stack and suspends inside a loop fiber. `delay()` is a timer. `all()` gates, settles every entry, then rethrows the first failure.
* **Addition**: [Workflow graph](workflows/graph.md) — 0.8 node graph copied onto 0.9. `Awaitable` is `Voyager\Contracts\IOPools\Promise`. A bare node calls `LoopRuntime::isolated()`; that class, the manager, and the provider are still ahead.
* **Creation**: First bundle for `venusian/framework` 0.9.0 — [framework](orientation/framework.md), [event loop](io-pools/event-loop.md), [worker pools](io-pools/worker-pools.md), [async](io-pools/async.md), [defer](io-pools/defer.md), [deferred log channel](log/deferred-channel.md), [cache stores](cache/stores.md), [cache defer](cache/defer.md), [Redis component](redis/component.md), [Redis push/pop](redis/push-pop.md), [Computer](console/computer.md).
* **Addition**: [Concurrency drivers](concurrency/drivers.md) — the component wired onto 0.9, plus the `defer()` helper and console binary helpers it needed. `runningInConsole()` is gone: every entry point is the console, so the question has one answer.
* **Update**: `AGENTS.md` points at [`.okf/index.md`](index.md). Concepts stay `draft`. The authoring agent does not set `stable`.
