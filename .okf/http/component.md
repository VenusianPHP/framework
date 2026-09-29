---
type: Module
title: Http client
description: Guzzle client behind app('http'). Sync sends block. async() on a bound loop answers with a loop promise. No facade.
resource: src/Voyager/Http/Client/Factory.php
tags: [http, guzzle, client]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: factory
    resource: src/Voyager/Http/Client/Factory.php
    title: Factory
  - id: pending
    resource: src/Voyager/Http/Client/PendingRequest.php
    title: PendingRequest
  - id: provider
    resource: src/Voyager/Http/HttpServiceProvider.php
    title: HttpServiceProvider
  - id: config
    resource: config/http.php
    title: http config
---

# Overview

`app('http')` is `Factory`; `app('http.async')` is `HttpAsyncManager`. `HttpServiceProvider` is deferred and on `DefaultProviders`. No `Http` facade.[^provider]

Sync sends block: Guzzle with its own handler, no loop.[^pending]

`async()` with a bound loop: the send starts now on the loop driver and returns a loop promise that follows the bare Guzzle promise. A failure rejects; a non-throwable rejection reason becomes `HttpClientException`. `Pool` and `Batch` hand in the loop handler themselves (Guzzle's own without a loop) and build lazily, keeping their concurrency cap.[^pending][^factory]

`Factory` type-hints `Voyager\Contracts\Events\Dispatcher`, which this tree does not define; the provider passes null, so no request/response signals fire.[^factory][^provider]

Config `config/http.php` key `http.async`: `default` (`HTTP_ASYNC_DRIVER`, `auto`), `drivers.curl` (`max_handles` 50, `multi_options`), `drivers.pcurl` (`max_handles` 50). Drivers: [async drivers](/http/async-drivers.md).[^config]

[^factory]: Factory
[^pending]: PendingRequest
[^provider]: HttpServiceProvider
[^config]: http config
