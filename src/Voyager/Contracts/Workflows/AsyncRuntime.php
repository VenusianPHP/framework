<?php

namespace Voyager\Contracts\Workflows;

use Closure;

/**
 * The execution strategy behind asynchronous nodes and flows.
 *
 * Every asynchronous behaviour in the Workflows package is expressed through
 * these five operations, so a runtime may be swapped without touching node or
 * flow code.
 */
interface AsyncRuntime
{
    /**
     * Begin the given work and return an awaitable for its result.
     */
    public function async(Closure $work): Awaitable;

    /**
     * Lift a plain value into an awaitable belonging to this runtime.
     *
     * Values that are already awaitables are returned unchanged.
     */
    public function resolve(mixed $value): Awaitable;

    /**
     * Resolve an awaitable to its value, blocking the current context.
     *
     * Values that are not awaitables are returned unchanged, which is what
     * allows node lifecycle methods to return either.
     *
     * @throws \Throwable The rejection reason, if the awaitable failed.
     */
    public function await(mixed $value): mixed;

    /**
     * Resolve many awaitables or closures, preserving keys.
     *
     * Every entry settles before the result is inspected. If any rejected, the
     * first rejection reason (in key order) is thrown.
     *
     * @param iterable<array-key, Awaitable|Closure> $awaitables
     * @param int|null $concurrency Maximum entries in flight, or null for no limit.
     */
    public function all(iterable $awaitables, ?int $concurrency = null): Awaitable;

    /**
     * Wait the given number of seconds without blocking sibling work.
     */
    public function delay(float $seconds): Awaitable;
}
