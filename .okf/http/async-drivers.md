---
type: Module
title: Http async drivers
description: curl holds the loop's sleep inside curl_multi_select; pcurl puts curl's own sockets and timer in the loop's wait. auto picks pcurl when loaded.
resource: src/Voyager/Http/Async/HttpAsyncManager.php
tags: [http, async, curl, pcurl, loop]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
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
  - id: inflight
    resource: src/Voyager/Http/Async/Concerns/TracksInFlight.php
    title: TracksInFlight
  - id: names
    resource: src/Voyager/Http/Async/AsyncResource.php
    title: AsyncResource
---

# Overview

`HttpAsyncManager::handler()` is null without a loop. `auto` builds `LoopPcurlHandler` when ext-pcurl is loaded, else `LoopCurlHandler`. `pcurl` without the extension throws `HttpClientException`. Both call `curl_multi_*`, so ext-curl is needed too.[^manager]

Both are Guzzle handlers (`TracksInFlight`). A request's Guzzle promise waits with `$loop->until()` until the transfer leaves the in-flight map; cancel removes the easy handle; a `delay` option starts it from `$loop->at()`. The handler registers on the loop at its first transfer (`http.curl` / `http.pcurl`) and forgets itself once none are in flight.[^inflight][^names]

# curl

`LoopCurlHandler` is a `Sleeper`. ext-curl never hands out curl's sockets, so while transfers are in flight it holds the loop's sleep inside `curl_multi_select()` and drives them with `curl_multi_exec()` each tick. Other wake sources get a glance after the select returns, so they can wait up to the pace.[^curl]

# pcurl

`LoopPcurlHandler` is a wake source and a deadline. ext-pcurl binds curl's socket and timer callbacks: each socket curl opens joins the loop's wait as readable and/or writable, exactly as curl asked; curl's timer is the handler's `dueAt()`. `fire()` and new transfers run `curlMultiSocketAction` with `PcurlSocket::TIMEOUT`.[^pcurl]

The loop waits on a `php://fd` duplicate of each socket. On `CURL_POLL_REMOVE` the duplicate is retired, not closed: it closes on the `wakes()` call after the one that dropped it, once the waiter has detached it; when the handler goes idle and the loop forgets it, a deferral closes what is left after the next sync. A pooled connection keeps curl's descriptor open, so closing first would leave epoll a stale entry for the socket (the next duplicate fails `EPOLL_CTL_ADD` with EEXIST, and the entry stays ready).[^pcurl]

ext-pcurl is 1:1 libcurl and defines no constants. The values live in userland int enums `PcurlOption`, `PcurlPoll`, `PcurlSelect`, `PcurlSocket`; pass them with `->value`.[^pcurl]

[^manager]: HttpAsyncManager
[^curl]: LoopCurlHandler
[^pcurl]: LoopPcurlHandler
[^inflight]: TracksInFlight
[^names]: AsyncResource
