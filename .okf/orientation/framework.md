---
type: Orientation
title: Framework
description: PHP 8.4 framework package venusian/framework at 0.10.0, the replace map, and which providers boot.
resource: composer.json
tags: [framework, voyager, boot]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: composer
    resource: composer.json
    title: Package manifest
  - id: providers
    resource: src/Voyager/Core/DefaultProviders.php
    title: Default provider list
  - id: instance
    resource: src/Voyager/Core/RenderedInstance.php
    title: RenderedInstance base providers and aliases
  - id: factory
    resource: src/Voyager/Core/Bootstrap/ConfigFactory.php
    title: ConfigFactory
---

# Overview

`venusian/framework` at `0.10.0`. PHP `^8.4|^8.5`.[^composer]

App object `Voyager\Core\RenderedInstance`. Container `Voyager\Vessel\ControlPanel`. `ConfigFactory::create()` binds the default exception handler when `withExceptions()` was not called, so a bare boot (pool worker, test app) still resolves `ExceptionHandler`.[^factory]

Root `replace` map: collections, conditionable, contracts, macroable, nuts-and-bolts, reflection. Every other component ships in this tree under `src/Voyager`. Config lives in root `config/` only; no component merges a config file of its own.[^composer]

Suggested, not required: `laudis/neo4j-php-client` (Graph connections), `pusher/pusher-php-server` (pusher/reverb broadcasting).[^composer]

# Base providers

`RenderedInstance` registers `SignalServiceProvider`, `LogServiceProvider`, `ContextServiceProvider` before the list below.[^instance]

# Providers that boot

In `DefaultProviders` order. `FoundationServiceProvider` is the one commented entry.[^providers]

| Provider | Role |
|---|---|
| `BusServiceProvider` | Command bus, database batch repository |
| `ConsoleSupportServiceProvider` | Computer console and its commands |
| `CacheServiceProvider` | Cache manager; array, database, file, null, redis stores; `async()` |
| `ConcurrencyServiceProvider` | `ConcurrencyManager` (`concurrency`); process, fork, sync, pool drivers |
| `DatabaseServiceProvider` | `app('db')`; Instrument; `via()` / `stream()` on worker pools |
| `MigrationServiceProvider` | Deferred; migrator, repository, creator, `migrate:*`, `make:migration` |
| `FilesystemServiceProvider` | `files`, `filesystem`, `Storage`; `via()` / `stream()` on worker pools |
| `GraphServiceProvider` | `neo4j` database driver; Cypher grammar |
| `HashServiceProvider` | `hash`; `makeAsync()` / `checkAsync()` |
| `EncryptionServiceProvider` | `encrypter` |
| `HttpServiceProvider` | `http`, `http.async`; loop drivers curl / pcurl |
| `LogServiceProvider` | `log`; `*Async()` writes on worker pools |
| `QueueServiceProvider` | `queue`, `queue.worker`, `queue.failer`; `async()` pushes |
| `RedisServiceProvider` | `redis`; loop pipes, list push/pop |
| `BroadcastServiceProvider` | `broadcast`; log, null, redis, pusher/reverb |
| `IOPoolsServiceProvider` | `event-loop`, `process-workers`, `thread-workers` when enabled |
| `PipelineServiceProvider` | `pipeline`, `Hub` |
| `SketchesServiceProvider` | Deferred; `sketches.registry`, `sketches.runner` |
| `WorkflowsServiceProvider` | `AsyncRuntimeManager`, `loop` runtime |

[^composer]: Package manifest
[^providers]: Default provider list
[^instance]: RenderedInstance base providers and aliases
[^factory]: ConfigFactory
