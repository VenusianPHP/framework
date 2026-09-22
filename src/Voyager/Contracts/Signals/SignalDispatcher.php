<?php

namespace Voyager\Contracts\Signals;

interface SignalDispatcher
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  callable|string|array  $events
     * @param  callable|string|array|null  $listener
     * @return void
     */
    public function listen(callable|string|array $events, mixed $listener = null): void;

    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners(string $event_name): bool;

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string  $subscriber
     * @return void
     */
    public function subscribe(object|string $subscriber): void;

    /**
     * Dispatch an event until the first non-null response is returned.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @return mixed
     */
    public function until(string|object $event, mixed $payload = []): mixed;

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return mixed Every listener's answer, in order; or, when halting, the first non-null one.
     */
    public function dispatch(string|object $event, mixed $payload = [], bool $halt = false): mixed;

    /**
     * Register an event and payload to be fired later.
     *
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function push(string $event, array $payload = []): void;

    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     * @return void
     */
    public function flush(string $event): void;

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     * @return void
     */
    public function forget(string $event): void;

    /**
     * Forget every queued listener.
     *
     * @return void
     */
    public function forgetPushed(): void;
}