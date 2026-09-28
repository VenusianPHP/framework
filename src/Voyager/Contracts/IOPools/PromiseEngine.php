<?php

namespace Voyager\Contracts\IOPools;

use Throwable;

/**
 * How to talk to one promise library. Holds no state: the library's promise object
 * is handed in on every call.
 */
interface PromiseEngine
{
    /** A new pending promise from the library. The only kind resolve() and reject() accept. */
    public function make(): object;

    /** A library promise that follows someone else's thenable. */
    public function adopt(object $thenable): object;

    public function resolve(object $inner, mixed $value): void;

    public function reject(object $inner, Throwable $reason): void;

    /** The library's then(): returns the library's next promise in the chain. */
    public function chain(object $inner, ?callable $on_fulfilled, ?callable $on_rejected): object;

    /** Run whatever callbacks the library has queued. */
    public function flush(): void;
}
