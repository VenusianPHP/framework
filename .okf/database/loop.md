---
type: Concept
title: Database on the loop
description: via() returns a promise of the blocking result. stream() delivers keyed pages as ModelChunk mail.
resource: src/Voyager/Database/IOPools/OffloadedQuery.php
tags: [database, iopools, via, stream]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T22:10:00Z }
sources:
  - id: via
    resource: src/Voyager/Database/Query/Builder.php
    title: Query\Builder::via
  - id: gig
    resource: src/Voyager/Database/IOPools/QueryGig.php
    title: QueryGig
  - id: offloaded
    resource: src/Voyager/Database/IOPools/OffloadedQuery.php
    title: OffloadedQuery
  - id: arrived
    resource: src/Voyager/Database/IOPools/Arrived.php
    title: Arrived
  - id: stream
    resource: src/Voyager/Database/IOPools/QueryStreamResource.php
    title: QueryStreamResource
  - id: connection
    resource: src/Voyager/Database/IOPools/OffloadedConnection.php
    title: OffloadedConnection
---

# via()

`via($target)` on `Query\Builder` or `Instrument\Builder` returns an `OffloadedQuery`. Every terminal is a promise of what the blocking call returns. The builder stays blocking. Target is a work-target name; omitted means the default (`sync` in tests, `pool` in the app).[^via][^offloaded]

Builders serialize by connection name. The worker re-attaches `app('db')->connection($name)`. An unnamed connection throws `LogicException` at the call. Closures the builder stores (eager loads, scopes, before/after query callbacks) wrap in `SerializableClosure`. `$onDelete`, local macros, clone callbacks, and property passthrough do not cross. `via()` refuses the terminals that would need them.[^gig][^via]

Refused, `BadMethodCallException`, point at `stream()`: `cursor`, `lazy`, `lazyById`, `lazyByIdDesc`, `chunk`, `chunkById`, `chunkByIdDesc`, `chunkMap`, `each`, `eachById`, `tap`, `when`, `unless`. They take a callback the worker would run, or they yield. Neither crosses back. `stream()` is the loop form.[^offloaded]

# Arrival

`OffloadedQuery::__call` returns `$promise->then(Arrived::models(...))`. `retrieved` fires once per model on the caller's dispatcher, including eager models, never on the worker (`QueryGig` wraps the terminal in `Model::withoutRetrieved`). Scalars, arrays, and base collections pass through. Nothing else differs from the blocking result. Bad SQL rejects with `RemoteException`.[^arrived][^gig]

# stream()

`stream($chunk = 1000, $column = null, $target = null)` on both builders returns a `QueryStreamResource`. Name is `query-stream:<connection>:<table>:<uuid>`. Keyed pages, one gig in flight, shaped like `orderedChunkById`: `orderBy($column)` then `where($column, '>', lastId)`. Default column is the model's qualified key, else `id`. Each page clones the builder, so an order already on the builder stays and the key order is appended last.[^stream]

Mail is `ModelChunk`: `connection`, `table`, `page` (1-based), `rows` (`Instrument\Collection` or base `Collection`), `last`. `done()` resolves with the row count. An empty stream resolves `0`, pumps no chunk, and forgets itself. A short page is last. A full page is held until the next page arrives, so an exact multiple is marked last and no empty chunk is mailed.[^stream]

The last page and `done()` settle in the same call. Same caveat as [file stream](../filesystem/file-stream.md): `pump()` runs after `flush()`, and `until()` / `await()` are quiet. Listen for `last`.

# Connection::via()

`Connection::via($target)` returns an `OffloadedConnection`. Allow-list (`OffloadableStatement`): `select`, `selectOne`, `selectFromWriteConnection`, `scalar`, `insert`, `update`, `delete`, `statement`, `affectingStatement`, `unprepared`. Anything else throws `BadMethodCallException`. A transaction, a cursor, or the PDO stays on one connection. Put the whole unit of work in one `ShouldPool` gig.[^connection]

# Graph

`Neo4jConnection extends Connection`, so `neo4j_connection()->via()->select($cypher)` is inherited. Nothing in `src/Voyager/Graph` adds `via()`. The worker must have the graph provider booted or it cannot resolve the named connection.

[^via]: Query\Builder::via
[^gig]: QueryGig
[^offloaded]: OffloadedQuery
[^arrived]: Arrived
[^stream]: QueryStreamResource
[^connection]: OffloadedConnection
