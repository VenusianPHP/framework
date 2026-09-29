<?php

namespace Voyager\Contracts\Workflows;

use Closure;
use Voyager\Contracts\IOPools\Promise;

interface AsyncRuntime
{
    /** Start $work now; the promise settles with its return (a returned promise is unwrapped). */
    public function async(Closure $work): Promise;

    /** A promise already holding $value, or $value itself when it is one. */
    public function resolve(mixed $value): Promise;

    /** Block (main stack) or suspend (loop fiber) until $value settles; plain values pass through. */
    public function await(mixed $value): mixed;

    /** Run every closure, at most $concurrency at once; results keyed like the input; first failure rethrown after all settle. */
    public function all(iterable $work, ?int $concurrency = null): Promise;

    /** A promise that resolves after $seconds. */
    public function delay(float $seconds): Promise;
}
