---
type: Module
title: Hashing
description: bcrypt, argon2i and argon2id behind HashManager; makeAsync() and checkAsync() hash on a worker pool.
resource: src/Voyager/Hashing/HashManager.php
tags: [hashing, bcrypt, argon, pool]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: manager
    resource: src/Voyager/Hashing/HashManager.php
    title: HashManager
  - id: gig
    resource: src/Voyager/Hashing/HashGig.php
    title: HashGig
  - id: check
    resource: src/Voyager/Hashing/CheckHashGig.php
    title: CheckHashGig
  - id: config
    resource: config/hashing.php
    title: hashing config
---

# Overview

`app('hash')` is `HashManager`; `app('hash.driver')` and `Hasher` the default hasher. Drivers `bcrypt` (default), `argon` (argon2i), `argon2id`. Config `config/hashing.php`. No `Hash` facade.[^manager][^config]

`make()` / `check()` block. A hash is CPU work, so async means another thread or process: `makeAsync($value, $options, $driver)` submits a `HashGig`, `checkAsync($value, $hashed, $options, $driver)` a `CheckHashGig`, each a promise. Pool: `thread-workers`, else `process-workers`, else `InvalidArgumentException`. Values are `SensitiveParameter`s.[^manager][^gig][^check]

`rehash_on_login` has no consumer. `HashManager::isHashed()` exists.

[^manager]: HashManager
[^gig]: HashGig
[^check]: CheckHashGig
[^config]: hashing config
