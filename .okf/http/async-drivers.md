---
type: Module
title: Http async drivers
description: curl holds the loop's sleep inside curl_multi_select; multicurl puts curl's own sockets and timer in the loop's wait. auto picks multicurl when loaded.
resource: src/Voyager/Http/Async/HttpAsyncManager.php
tags: [http, async, curl, multicurl, loop]
status: draft
generated: { by: claude-code/claude-opus-5.5, at: 2026-11-23T22:39:13Z }
sources:
  - id: manager
    resource: src/Voyager/Http/Async/HttpAsyncManager.php
    title: HttpAsyncManager
  - id: curl
    resource: src/Voyager/Http/Async/LoopCurlHandler.php
    title: LoopCurlHandler
  - id: multicurl
    resource: src/Voyager/Http/Async/LoopMulticurlHandler.php
    title: LoopMulticurlHandler
  - id: inflight
    resource: src/Voyager/Http/Async/Concerns/TracksInFlight.php
    title: TracksInFlight
  - id: names
    resource: src/Voyager/Http/Async/AsyncResource.php
    title: AsyncResource
---

# Overview

`HttpAsyncManager::handler()` is null without a loop. `auto` builds `LoopMulticurlHandler` when ext-multicurl is loaded, else `LoopCurlHandler`. `multicurl` without the extension throws `HttpClientException`. Both call `curl_multi_*`, so ext-curl is needed too.[^manager]

Both are Guzzle handlers (`TracksInFlight`). A request's Guzzle promise waits with `$loop->until()` until the transfer leaves the in-flight map; cancel removes the easy handle; a `delay` option starts it from `$loop->at()`. The handler registers on the loop at its first transfer (`http.curl` / `http.multicurl`) and forgets itself once none are in flight.[^inflight][^names]

# curl

`LoopCurlHandler` is a `Sleeper`. ext-curl never hands out curl's sockets, so while transfers are in flight it holds the loop's sleep inside `curl_multi_select()` and drives them with `curl_multi_exec()` each tick. Other wake sources get a glance after the select returns, so they can wait up to the pace.[^curl]

# multicurl

`LoopMulticurlHandler` is a wake source and a deadline. ext-multicurl binds curl's socket and timer callbacks: each socket curl opens joins the loop's wait as readable and/or writable, exactly as curl asked; curl's timer is the handler's `dueAt()`. `fire()` and new transfers run `curlMultiSocketAction` with `MulticurlSocket::TIMEOUT`.[^multicurl]

ext-multicurl is 1:1 libcurl and defines no constants. The values live in userland int enums `MulticurlOption`, `MulticurlPoll`, `MulticurlSelect`, `MulticurlSocket`; pass them with `->value`.[^multicurl]

[^manager]: HttpAsyncManager
[^curl]: LoopCurlHandler
[^multicurl]: LoopMulticurlHandler
[^inflight]: TracksInFlight
[^names]: AsyncResource
