---
type: Component
title: voyager/io-pools
description: >-
  Non-blocking I/O pools riding a cooperative loop tick: the Tickable law,
  the event queue, and the HttpPool over curl_multi.
tags: [io-pools, async, events, tick]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-31T00:30:00Z" }
sources:
  - id: pool
    resource: src/Voyager/IOPools/HttpPool.php
    title: HttpPool
  - id: queue
    resource: src/Voyager/IOPools/EventQueue.php
    title: EventQueue
  - id: tests
    resource: tests/IOPools
    title: IOPools tests
---

# One law

`Tickable::tick()` returns fast, always. The loop\'s budget belongs to
whoever owns the loop (a sketch, Surface\'s shuttle); a tickable that waits
has broken the contract. Everything else in the component serves that law.

# Pieces

| | |
|---|---|
| `Event` | family + name + payload; domain layers subclass (Surface adds the window) |
| `EventQueue` / `EventSink` | mailbox with a push-only producer face; drain keyed by name, same-name-per-tick collapses to last |
| `TickRoster` | the one list a loop owner pumps; registration order is pump order |
| `HttpPool` + `PendingCall` + `HttpResult` | named non-blocking calls; completion = raw-named \'task\' event AND optional hook, both lanes always |
| `HttpDriver` | transport seam; `MultiCurlDriver` ships (the kernel is the parallelism), slot reserved for an fd/serial pool sibling and an ext-parallel COMPUTE driver |

# Decisions

- `HttpResult::$ok` is transport truth only; a 404 is a successful
  conversation and the consumer judges status.
- One in-flight call per name, duplicates refused loudly, name freed on
  settle.
- No threads, no fork (GUI engines are fork-unsafe post-init); kernel
  multiplexing is the parallelism.
- Born in venusian/surface (0.8, 2026-08-30) and moved down whole with its
  tests so headless sketches get async without installing windowing.

# Not this component

Not an event bus, not a promise library, not a scheduler. Fibers are later
sugar over the same pool, not the foundation.

[^pool]: HttpPool
[^queue]: EventQueue
[^tests]: IOPools tests
