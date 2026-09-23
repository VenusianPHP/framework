---
type: Module
title: Http client
description: Guzzle client behind app('http'). Sync sends stay blocking. No facade.
resource: src/Voyager/Http/Client/Factory.php
tags: [http, guzzle, client]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T19:50:00Z }
sources:
  - id: factory
    resource: src/Voyager/Http/Client/Factory.php
    title: Factory
  - id: provider
    resource: src/Voyager/Http/HttpServiceProvider.php
    title: HttpServiceProvider
  - id: config
    resource: src/Voyager/Http/config/http.php
    title: http config
  - id: dispatcher
    resource: src/Voyager/Contracts/Events/Dispatcher.php
    title: Dispatcher shim
  - id: forwards
    resource: src/Voyager/NutsAndBolts/Concerns/ForwardsCalls.php
    title: ForwardsCalls shim
---

# Overview

`app('http')` is a `Factory`. `app('http.async')` is the `HttpAsyncManager` the factory uses for async sends. There is no `Http` facade.[^factory][^provider]

A sync send is the 0.8 path: Guzzle, blocking, no loop driver. `async()` with a bound loop returns `Loop::adopt()` of the Guzzle promise. `Pool` and `Batch` keep that Guzzle promise via `getPromise()` so their concurrency cap still applies. `fake()` never registers a driver on the loop.[^factory]

# Wiring

`HttpServiceProvider` implements `DeferrableProvider` and is on `DefaultProviders`. It merges `src/Voyager/Http/config/http.php` under `http` and provides `http` and `http.async`. The computer also has `config/http.php`.[^provider][^config]

The client type-hints `Voyager\Contracts\Events\Dispatcher`. That interface is a shim copied so the 0.8 client compiles; this package does not boot an events dispatcher. `ForwardsCalls` is the same kind of shim, used by the fluent promise.[^dispatcher][^forwards]

# Async config

`http.async.default` is `env('HTTP_ASYNC_DRIVER', 'curl')`. Drivers are `curl` and `pcurl`. Sync requests never read it. How the two drivers sit on the loop is [async drivers](/http/async-drivers.md).[^config]

[^factory]: Factory
[^provider]: HttpServiceProvider
[^config]: http config
[^dispatcher]: Dispatcher shim
[^forwards]: ForwardsCalls shim
