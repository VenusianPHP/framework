---
type: Concept
title: Database on the loop
description: via() runs a terminal, statement or transaction as a gig on a worker pool; stream() pages a query as QueryChunk mail; per-connection lanes keep reads and writes in order with blocking calls.
resource: src/Voyager/Database/IOPools/Offload.php
tags: [database, iopools, via, stream, lanes]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: offload
    resource: src/Voyager/Database/IOPools/Offload.php
    title: Offload
  - id: lanes
    resource: src/Voyager/Database/IOPools/ConnectionLanes.php
    title: ConnectionLanes
  - id: worker
    resource: src/Voyager/Database/IOPools/WorkerConnection.php
    title: WorkerConnection
  - id: query
    resource: src/Voyager/Database/IOPools/OffloadedQuery.php
    title: OffloadedQuery
  - id: connection
    resource: src/Voyager/Database/IOPools/OffloadedConnection.php
    title: OffloadedConnection
  - id: transaction
    resource: src/Voyager/Database/IOPools/TransactionGig.php
    title: TransactionGig
  - id: stream
    resource: src/Voyager/Database/IOPools/QueryStream.php
    title: QueryStream
  - id: chunk
    resource: src/Voyager/Database/IOPools/QueryChunk.php
    title: QueryChunk
  - id: arrived
    resource: src/Voyager/Database/IOPools/Arrived.php
    title: Arrived
  - id: settle
    resource: src/Voyager/Database/Connection.php
    title: Connection::settleOffloaded
---

# Overview

PDO never yields, so a query goes async as a gig: the worker runs the blocking call. `via(?string $pool)` on `Query\Builder`, `Instrument\Builder`, and `Connection`. `$pool`: `'thread'`, `'process'`, or null for thread workers when bound, else process workers.[^offload]

# Refused up front

`Offload::for()` throws `LogicException` for: a connection with no name; SQLite `:memory:` or `''` (a worker would open a different, empty database); a connection with a transaction open (the worker's write would land outside it; offload the whole transaction). A missing or disabled pool throws `InvalidArgumentException`.[^offload]

# What crosses

Each gig carries the connection name and its config, minus `name`. `WorkerConnection::resolve()` sets that config in the worker and purges the connection when it differs, so a database changed at runtime is the one the worker queries.[^worker]

Builders serialize by connection name when the call is made; later changes to the builder don't reach the worker. Models come back serialized.[^query]

# via() on a builder

`->where(...)->via()->get()`: every terminal (`get`, `first`, `find`, `pluck`, `count`, `exists`, `paginate`, `insert`, `update`, `delete`, `create`, …) answers with a promise of the blocking result. A method that builds rather than runs rejects with `LogicException`. Callback or generator methods throw `BadMethodCallException`: `cursor`, `lazy*`, `chunk*`, `chunkMap`, `each*`, `tap`, `when`, `unless`.[^query]

`retrieved` fires in the caller, once per model, on arrival (`Arrived::models`); the worker suppresses it.[^arrived]

# via() on a connection

Raw statements: `select`, `selectOne`, `selectFromWriteConnection`, `scalar` read; `insert`, `update`, `delete`, `statement`, `affectingStatement`, `unprepared` write. Anything else throws `BadMethodCallException`.[^connection]

`via()->transaction(Closure $callback, int $attempts = 1)`: the closure crosses as a `SerializableClosure` and runs inside `$connection->transaction()` in the worker, taking that connection as its argument. A closure keeps the class it was made in as its scope, so that class must load in the worker.[^transaction]

# Order

`ConnectionLanes`, one per loop, orders a connection's offloaded calls as a reader-writer lane: reads run beside reads; a write waits for everything before it, and everything after waits for it. Named per connection, so different connections run side by side.[^lanes]

Blocking calls join the lanes: `select` and `cursor` wait for offloaded writes; `statement`, `affectingStatement`, `unprepared` and `beginTransaction()` wait for everything offloaded before them (`Connection::settleOffloaded`).[^settle]

# stream()

`stream(int $chunk = 1000, ?string $column = null, ?string $pool = null): Promise` on both builders. Keyset pages ordered by `$column` (the model's qualified key, or `id`), each page a read through the lanes. Each page posts a `QueryChunk` as loop mail named `db-chunk:{connection}:{table}`: `page`, `rows` (models for an Instrument builder), `last`. A full page is held until the next arrives, so an exact multiple ends on a marked page; no rows sends one empty last chunk. The promise resolves with the row count. `$chunk < 1` throws `InvalidArgumentException`.[^stream][^chunk]

[^offload]: Offload
[^lanes]: ConnectionLanes
[^worker]: WorkerConnection
[^query]: OffloadedQuery
[^connection]: OffloadedConnection
[^transaction]: TransactionGig
[^stream]: QueryStream
[^chunk]: QueryChunk
[^arrived]: Arrived
[^settle]: Connection::settleOffloaded
