---
type: Orientation
title: Framework
description: PHP 8.4 framework package venusian/framework at 0.9.0, and which providers actually boot.
resource: composer.json
tags: [framework, voyager, boot]
status: draft
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

The application object is `Voyager\Core\RenderedInstance`. The container is `Voyager\Vessel\ControlPanel`. Providers that are commented out in `DefaultProviders` do not boot.[^providers]

# Providers that boot

| Provider | Role |
|---|---|
| `BusServiceProvider` | Command bus |
| `PipelineServiceProvider` | Pipeline |
| `ConsoleSupportServiceProvider` | Computer console and its commands |
| `CacheServiceProvider` | Cache manager and stores |
| `FoundationServiceProvider` | Core framework services |
| `FilesystemServiceProvider` | Flysystem disks behind `app('filesystem')`; `Storage` singleton behind `storage()`; local required, s3/ftp/sftp suggested |
| `HashServiceProvider` | bcrypt, argon2i, and argon2id behind `app('hash')` |
| `HttpServiceProvider` | Guzzle client behind app('http'); loop async drivers curl / pcurl |
| `ConcurrencyServiceProvider` | Concurrent `run()` / `defer()` |
| `DatabaseServiceProvider` | Connections, Instrument, schema builder behind app('db'); sqlite default; via() / stream() on the loop |
| `MigrationServiceProvider` | Deferred; migrator, repository, creator, migrate:* commands |
| `LogServiceProvider` | Log manager |
| `QueueServiceProvider` | Queue manager |
| `RedisServiceProvider` | Redis manager |
| `BroadcastServiceProvider` | Outbound `BroadcastManager`; default driver `null` |
| `IOPoolsServiceProvider` | Event loop, worker pool, and `app('work-targets')` |
| `WorkflowsServiceProvider` | Node-graph runtime manager (`loop` / `isolated`) |
| `SketchesServiceProvider` | Deferred; sketch registry and the runner behind app('sketches.runner') |

# Opt-in

`GraphServiceProvider` is not on `DefaultProviders`. Register it for `database.connections.neo4j`. `cypher()` autoloads. `neo4j_connection()` throws until the provider is registered.[^providers]

[^composer]: Package manifest
[^providers]: Default provider list
