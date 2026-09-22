<?php

namespace Voyager\Contracts\IOPools;

use Throwable;

/**
 * How to talk to one promise library. Shared by every promise, so it holds
 * no state: the library's promise object is handed in on every call.
 */
interface PromiseEngine
{
    /**
     * A new pending promise from the library. This is the writable one.
     */
    public function make(): object;

    public function resolve(object $inner, mixed $value): void;

    public function reject(object $inner, Throwable $reason): void;

    /**
     * The library's then(): returns the library's next promise in the chain.
     */
    public function chain(object $inner, ?callable $on_fulfilled, ?callable $on_rejected): object;

    /**
     * Run whatever callbacks the library has queued. Called once a turn, and before a wait.
     */
    public function flush(): void;
}
