---
type: Orientation
title: Framework
description: PHP 8.4 framework package venusian/framework at 0.9.0, and which providers actually boot.
resource: composer.json
tags: [framework, voyager, boot]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: grok-4.7/cursor, at: 2026-09-22T22:10:00Z }
sources:
  - id: composer
    resource: composer.json
    title: Package manifest
  - id: providers
    resource: src/Voyager/Core/DefaultProviders.php
    title: Default provider list
---

# Overview

`venusian/framework` is the Venusian PHP framework. The package version in the manifest is `0.9.0`. It requires PHP `^8.4|^8.5`.[^composer]

The application object is `Voyager\Core\RenderedInstance`. The container is `Voyager\Vessel\ControlPanel`. The table below is the boot list, in order. Nothing on it is commented out. Providers that are not on the list do not boot.[^providers]

The root `replace` map lists the `venusian-voyager/*` packages this tree ships: broadcasting, bus, cache, collections, concurrency, conditionable, config, console, contracts, database, filesystem, graph, hashing, http, io-pools, log, macroable, nuts-and-bolts, pagination, pipeline, queue, redis, reflection, signals, sketches, vessel, workflows. Graph is in that map and still opt-in as a provider.[^composer]

# Providers that boot

| Provider | Role |
|---|---|
| `BusServiceProvider` | Command bus |
| `ConsoleSupportServiceProvider` | Computer console and its commands |
| `CacheServiceProvider` | Cache manager and stores |
| `ConcurrencyServiceProvider` | Concurrent `run()` / `defer()` |
| `DatabaseServiceProvider` | Connections, Instrument, schema builder behind app('db'); sqlite default; via() / stream() on the loop |
| `MigrationServiceProvider` | Deferred; migrator, repository, creator, `migrate:*` and `make:migration` |
| `FilesystemServiceProvider` | Flysystem disks behind `app('filesystem')`; `Storage` singleton behind `storage()`; local required, s3/ftp/sftp suggested |
| `FoundationServiceProvider` | Core framework services |
| `HashServiceProvider` | bcrypt, argon2i, and argon2id behind `app('hash')` |
| `HttpServiceProvider` | Guzzle client behind app('http'); loop async drivers curl / pcurl |
| `LogServiceProvider` | Log manager |
| `QueueServiceProvider` | Queue manager |
| `RedisServiceProvider` | Redis manager |
| `BroadcastServiceProvider` | Outbound `BroadcastManager`; default driver `null` |
| `IOPoolsServiceProvider` | Event loop, worker pool, and `app('work-targets')` |
| `PipelineServiceProvider` | Pipeline |
| `SketchesServiceProvider` | Deferred; sketch registry and the runner behind app('sketches.runner') |
| `WorkflowsServiceProvider` | Node-graph runtime manager (`loop` / `isolated`) |

# Opt-in

`GraphServiceProvider` is not on `DefaultProviders`. Register it for `database.connections.neo4j`. `cypher()` autoloads. `neo4j_connection()` throws `RuntimeException` when the resolved connection is not a `Neo4jConnection`. A missing `neo4j` connection key fails earlier inside `DatabaseManager`.[^providers]

[^composer]: Package manifest
[^providers]: Default provider list
