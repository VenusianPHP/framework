---
type: Module
title: Hashing
description: bcrypt, argon2i, and argon2id behind HashManager, plus HashGig for the worker pool.
resource: src/Voyager/Hashing/HashManager.php
tags: [hashing, bcrypt, argon, pool]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T15:45:00Z }
sources:
  - id: manager
    resource: src/Voyager/Hashing/HashManager.php
    title: HashManager
  - id: provider
    resource: src/Voyager/Hashing/HashServiceProvider.php
    title: HashServiceProvider
  - id: gig
    resource: src/Voyager/Hashing/HashGig.php
    title: HashGig
  - id: config
    resource: config/hashing.php
    title: hashing config
  - id: aliases
    resource: src/Voyager/Core/Concerns/InstanceBootstrapping.php
    title: hash aliases
---

# Overview

`app('hash')` is a `HashManager`. `app(Hasher::class)` and `app('hash.driver')` are the default hasher. Drivers are `bcrypt` (default), `argon` (argon2i), and `argon2id`.[^manager][^aliases]

`make()` and `check()` block. A hash is CPU work, so there is no deferred hash call. Off the main thread, submit a `HashGig`.[^gig]

There is no `Hash` facade.

# Wiring

`HashServiceProvider` is on `DefaultProviders` and merges `src/Voyager/Hashing/config/hashing.php` under `hashing`. The computer also has `config/hashing.php`.[^provider][^config]

# HashGig

`HashGig implements ShouldPool`. The constructor takes the value, an optional driver (`null` means `config('hashing.driver')`), and options. `handle()` calls `app('hash')->driver($driver)->make($value, $options)` inside the worker. That resolves only because both worker entry points now run the console kernel's bootstrappers; see [worker pools](/io-pools/worker-pools.md).[^gig]

```php
$hash = $pool->submit(new HashGig($value))->wait();
```

The value is a `SensitiveParameter`.

# Out of scope

`rehash_on_login` is a config key with no consumer yet. Model `isHashed` is not part of this component. `HashManager::isHashed()` exists on the manager.

[^manager]: HashManager
[^provider]: HashServiceProvider
[^gig]: HashGig
[^config]: hashing config
[^aliases]: hash aliases
