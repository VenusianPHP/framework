---
type: Module
title: Redis component
description: Connections, manager, and the provider that boots Redis.
resource: src/Voyager/Redis/RedisServiceProvider.php
tags: [redis]
status: draft
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: provider
    resource: src/Voyager/Redis/RedisServiceProvider.php
    title: RedisServiceProvider
  - id: composer
    resource: composer.json
    title: predis is require-dev
---

# Overview

`RedisServiceProvider` is in the default provider list, so the Redis manager boots with the framework.

Two clients exist. `PhpRedisConnector` talks to `ext-redis`. `PredisConnector` talks to `predis/predis`, which is a dev dependency at `^2.4|^3.6`, not a production requirement.[^composer]

Command lifecycle signals live under `Voyager\Redis\Signals`: `CommandExecuted` and `CommandFailed`. The 0.8 name `Events` is `Signals` here.

[^composer]: predis is require-dev
