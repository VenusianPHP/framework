---
type: Module
title: Queued listeners
description: A ShouldQueue listener or a QueuedClosure goes on the queue as a CallQueuedListener job; unique listeners lock before pushing.
resource: src/Voyager/Signals/SignalDispatcher.php
tags: [signals, queue, listeners]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: dispatcher
    resource: src/Voyager/Signals/SignalDispatcher.php
    title: SignalDispatcher
  - id: job
    resource: src/Voyager/Signals/CallQueuedListener.php
    title: CallQueuedListener
  - id: closure
    resource: src/Voyager/Signals/QueuedClosure.php
    title: QueuedClosure
  - id: invoke
    resource: src/Voyager/Signals/InvokeQueuedClosure.php
    title: InvokeQueuedClosure
  - id: provider
    resource: src/Voyager/Signals/SignalServiceProvider.php
    title: SignalServiceProvider
---

# Overview

`app('signals')` is `SignalDispatcher` (no `events` binding). A `NamedSignal` dispatches under its `name()`, anything else under its class. `SignalServiceProvider` sets the queue resolver (`Queue\Factory`) and the transaction manager resolver (`db.transactions` when bound).[^dispatcher][^provider]

# ShouldQueue listeners

A listener class implementing `ShouldQueue` is not called; `CallQueuedListener($class, $method, $arguments)` is pushed instead. `shouldQueue($signal)` on the listener can decline per signal.[^dispatcher]

Read off the listener without its constructor: `connection` / `viaConnection()`, `queue` / `viaQueue()`, `delay` / `withDelay()`, `afterCommit` or `ShouldQueueAfterCommit`, `backoff`, `tries`, `maxExceptions`, `retryUntil()`, `timeout`, `failOnTimeout`, `ShouldBeEncrypted`, `messageGroup`, `deduplicator` / `deduplicationId`, `middleware`.[^dispatcher]

A `ShouldBeUnique` listener takes a `UniqueLock` in the cache before pushing; held, nothing is pushed. `uniqueId()`, `uniqueFor()`, `uniqueVia()` are read from the listener with the signal's arguments.[^dispatcher][^job]

`ShouldQueue` extends `Handleable::handle(): mixed`, so a queued listener declares `handle(?Signal $signal = null): mixed`. The worker builds it through the container, calls it with the arguments, sets the job on it when it uses `InteractsWithQueue`; `failed($e)` gets the arguments and the throwable.[^job]

# Queued closures

`listen(Signal::class, new QueuedClosure($closure))`: `onConnection()`, `onQueue()`, `delay()`, `onGroup()`, `withDeduplicator()`, `catch()`. Dispatch pushes `CallQueuedListener(InvokeQueuedClosure::class, …)` with the closure and catch callbacks as `SerializableClosure`s. On failure the catch callbacks get the arguments and the throwable.[^closure][^invoke]

[^dispatcher]: SignalDispatcher
[^job]: CallQueuedListener
[^closure]: QueuedClosure
[^invoke]: InvokeQueuedClosure
[^provider]: SignalServiceProvider
