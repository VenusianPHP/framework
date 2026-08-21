---
type: PHP Package
title: voyager/broadcasting
description: Event broadcasting (Pusher, Redis, Log, Null). Ably and incoming channel-auth are cut.
resource: ../../src/Voyager/Broadcasting
tags: [php, package, voyager, broadcasting, events]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Broadcasting
    title: Broadcasting package source (20 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Broadcasting/composer.json
    title: voyager/broadcasting composer.json
---

# Overview

20 PHP files under `src/Voyager/Broadcasting/`. Manifest `extra.venusian`
records `laravel/framework@v12.67.0` `src/Illuminate/Broadcasting`.[^package-manifest]

Drivers on disk: Log, Null, Pusher, Redis. Default connection in
`config/broadcasting.php` is `null`. `BroadcastServiceProvider` is in
`DefaultProviders`.

# Cuts

Ably is absent. `Contracts\Broadcasting\Broadcaster` exposes `broadcast()`
only. No `BroadcastController`; no `BroadcastManager::routes()` /
`userRoutes()` / `channelRoutes()` / `socket()`. Four upstream test files are
cut — `tests/Broadcasting/README.md`.

# Related

- [Known gaps](/known-gaps.md)
- [voyager/contracts](contracts.md)

[^package-source]: Broadcasting package source (20 PHP files)
[^package-manifest]: voyager/broadcasting composer.json
