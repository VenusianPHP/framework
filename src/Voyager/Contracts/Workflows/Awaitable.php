<?php

namespace Voyager\Contracts\Workflows;

/**
 * A pending value produced by an AsyncRuntime.
 *
 * Implementations wrap whatever future the underlying runtime uses. Only the
 * runtime that produced an awaitable is able to resolve it, so awaitables are
 * never portable between runtimes.
 */
interface Awaitable
{
    /**
     * Attach handlers to run once this awaitable settles.
     *
     * A handler may return a plain value or another awaitable; both are
     * flattened into the returned awaitable.
     *
     * @param callable(mixed): mixed|null $onFulfilled
     * @param callable(\Throwable): mixed|null $onRejected
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Awaitable;
}
