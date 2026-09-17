---
type: Component
title: voyager/io-pools
description: >-
  The IOPool dock: named resource drivers ticked on a cooperative loop, and
  one mail bag drained by the loop's single consumer.
tags: [io-pools, async, events, tick, http, redis]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-31T00:30:00Z" }
revised: { by: claude-opus-5/claude-code, at: "2026-09-13T00:00:00Z", note: "rewritten for the dock; HttpPool, EventQueue and TickRoster are gone" }
sources:
  - id: dock
    resource: src/Voyager/IOPools/IOPoolDock.php
    title: IOPoolDock
  - id: provider
    resource: src/Voyager/IOPools/IOPoolsServiceProvider.php
    title: IOPoolsServiceProvider
  - id: http
    resource: src/Voyager/IOPools/Drivers/MultiCurlResourceDriver.php
    title: MultiCurlResourceDriver
  - id: redis
    resource: src/Voyager/IOPools/Drivers/RedisResourceDriver.php
    title: RedisResourceDriver
  - id: contracts
    resource: src/Voyager/Contracts/IOPools
    title: IOPools contracts
  - id: config
    resource: config/io-pools.php
    title: io-pools config
  - id: tests
    resource: tests/IOPools
    title: IOPools tests
---

# One law

`tick()` returns fast, always. The loop's budget belongs to whoever owns
the loop (a sketch; Surface's `LiveApplication`). A resource that waits has
broken the contract. `IOResourceDriver extends Tickable` — every resource
lives by it.[^contracts]

# The dock

`IOPoolDock`, bound `io-pool`, alias `IOPool`; the provider is on
`DefaultProviders`.[^provider]

| Method | Does |
|---|---|
| `resource($name, $driver)` | register an `IOResourceDriver`; `resources()` lists them |
| `pump()` | `tick()` every resource, registration order |
| `push(QueuedIO)` | append mail to the bag |
| `drain()` | hand back the bag (`IOEventBag`), start a fresh one |
| `http()` / `async()` | the `http` / `async` resource, or null |
| `__call` | any resource by name — `$dock->os()`[^dock] |

The bag is an ordered list: same-shape mail stacks, never
coalesces.[^tests] `IOEventBag::dispatch()` fires each entry through
`event()`.

Boot: `register()` binds the dock with no resources; `boot()` runs
`bootResources(config('io-pools'))`, minting `http` and/or `async` when
`enabled`. Shipped defaults: `http` on (multi-curl), `async` off
(redis).[^config]

# Mail species

All mail implements `QueuedIO`, a marker with no shape.[^contracts]

| Contract | Meaning | Shipped |
|---|---|---|
| `Completion` (`ok()`) | solicited — the result of asked-for work | `HttpResult` |
| `Occurrence` | unsolicited — the world did something | `RedisMessage`; Surface's window / view / menu mail |
| `Sendable` | crosses a process boundary (`toSendable` / `fromSendable`) | — |

Listen on the interface to hear a whole species regardless of wire.

# http resource

`HttpResourceDriver`: `fetch($name, $url, $headers, $params)`,
`post($name, $url, $headers, $body)`,
`call($name, $url, $method, $headers, $body)` → `Presumption`; plus
`inFlight($name)` and `progress()`. `MultiCurlResourceDriver`
ships.[^http]

- One in-flight call per name. A duplicate throws `IOPoolsException`; the
  name frees on settle. `inFlight()` is the door to coalesce identical
  requests instead of throwing.
- Completion: `HttpResult` pushed into the dock **and**
  `Presumption::settle()` fires `onSuccess` or `onFail` by `ok()`. Both
  inside the pump.
- An `envelope` callable swaps domain mail into the dock; the Presumption
  still settles on the raw result.
- `ok` is transport truth (`CURLE_OK`). A 404 is `ok`; the consumer judges
  status.
- Progress is hook-only — `onProgress($now, $total)`, only when the byte
  count moves, never mail. `total` is 0 until the server declares a length.
- curl follows redirects, 10 s connect timeout, aborts a stall under
  1 KiB/s for 120 s.

# async resource

`AsyncResourceDriver`: out-of-process mail. `post(Sendable, ?key)` puts a
JSON envelope (`class` + `toSendable()`) on the wire — never native
`serialize()`. `tick()` sweeps only what is already buffered.[^redis]

`RedisResourceDriver` ships (needs `voyager/redis`, suggested): `rpush` /
`lpop` on a list key, at most `batch` (default 64) per tick; `key()`
retargets the sweep. Anything that cannot be rebuilt as a `Sendable`
`QueuedIO` arrives as `RedisMessage($key, $raw)` — the driver never eats
mail.

# Decisions

- No threads, no fork (GUI engines are fork-unsafe post-init); kernel
  multiplexing is the parallelism.
- Born in venusian/surface (0.8, 2026-08-30), moved down so headless
  sketches get async without windowing. Surface registers its engine pump
  on the dock as the `os` resource.

# Not this component

Not an event bus, not a promise library, not a scheduler. Fibers are later
sugar over the same dock, not the foundation.

[^dock]: IOPoolDock
[^provider]: IOPoolsServiceProvider
[^http]: MultiCurlResourceDriver
[^redis]: RedisResourceDriver
[^contracts]: IOPools contracts
[^config]: io-pools config
[^tests]: IOPools tests
