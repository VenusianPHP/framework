---
type: Module
title: Http async drivers
description: curl and pcurl loop handlers behind one Guzzle promise bridge.
resource: src/Voyager/Http/Async/HttpAsyncManager.php
tags: [http, async, curl, pcurl, loop]
status: draft
generated: { by: grok-4.7/cursor, at: 2026-09-22T19:50:00Z }
sources:
  - id: manager
    resource: src/Voyager/Http/Async/HttpAsyncManager.php
    title: HttpAsyncManager
  - id: curl
    resource: src/Voyager/Http/Async/LoopCurlHandler.php
    title: LoopCurlHandler
  - id: pcurl
    resource: src/Voyager/Http/Async/LoopPcurlHandler.php
    title: LoopPcurlHandler
  - id: loop
    resource: src/Voyager/IOPools/EventLoop.php
    title: EventLoop
  - id: inflight
    resource: src/Voyager/Http/Async/Concerns/TracksInFlight.php
    title: TracksInFlight
  - id: names
    resource: src/Voyager/Http/Async/AsyncResource.php
    title: AsyncResource
---

# Overview

`HttpAsyncManager` reads `http.async.default`. `handler()` is null when no loop is bound, and then `Pool` / `Batch` use Guzzle's own handler. With a loop, `curl` builds a `LoopCurlHandler` and `pcurl` builds a `LoopPcurlHandler`. `pcurl` throws `HttpClientException` when ext-pcurl is not loaded. ext-pcurl itself needs ext-curl loaded first.[^manager]

Resource names are `AsyncResource`: `CURL = http.curl`, `PCURL = http.pcurl`.[^names]

# Bridge

Both handlers are Guzzle handlers. The promise they return waits with `$loop->until()` until that transfer leaves the in-flight map, and cancels by removing the easy handle. `CurlFactory::finish()` runs from `harvest()` inside `tick()` (or from the pcurl timer) and resolves or rejects that promise. `PendingRequest::send()` on a bound loop returns `$loop->adopt()` of it, a `Voyager\Contracts\IOPools\Promise`. `getPromise()` keeps the Guzzle promise for `Pool` and `Batch`.[^inflight][^loop]

The handler calls `resource()` on the first in-flight request and `forget()` as soon as nothing is in flight.[^inflight]

# curl

`LoopCurlHandler` is `Tickable` and `Sleepable`. While it is registered it owns the loop's single sleeper slot and polls with `curl_multi_select`. `tick()` is `curl_multi_exec` plus `harvest()`.[^curl]

**Trap.** `forget()` clears the sleeper slot. The notebook does not put back a sleeper this driver displaced. That is current behaviour.[^curl]

# pcurl

`LoopPcurlHandler` is `StreamWatchable` only, so it does not take the sleeper slot and it is not ticked every turn. It ticks when one of its streams fires, or when curl's timer fires `curl_multi_socket_action` with `PcurlSocket::TIMEOUT`.[^pcurl]

ext-pcurl is 1:1 libcurl and defines no constants. Callbacks are `Pcurl\Multi::curlMultiSetopt` with `PcurlOption::SOCKET_FUNCTION` (20001) and `PcurlOption::TIMER_FUNCTION` (20004). Socket progress is `curlMultiSocketAction`. A watched socket is `fopen('php://fd/'.$fd, 'r+')`, closed on `PcurlPoll::REMOVE`.[^pcurl]

libcurl's poll, select, and socket values are the int enums `PcurlPoll`, `PcurlSelect`, and `PcurlSocket`. Compare and pass them with `->value`.[^pcurl]

**Trap.** The loop's `stream_select` watches reads. A socket curl marks `PcurlPoll::OUT` or `INOUT` is not writable-visible to that select, and curl then replaces a `0` ms timer with the connect timeout. The handler services that socket on the next turn with `PcurlSelect::OUT` (or `IN|OUT`). Without that, a later request on the same multi sits until cURL error 28.[^pcurl][^loop]

[^manager]: HttpAsyncManager
[^curl]: LoopCurlHandler
[^pcurl]: LoopPcurlHandler
[^loop]: EventLoop
[^inflight]: TracksInFlight
[^names]: AsyncResource
