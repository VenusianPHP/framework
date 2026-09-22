<?php

namespace Voyager\Contracts\IOPools;

use Throwable;

interface Promise
{
    public function then(callable $callable): Promise;
    public function error(callable $callable): Promise;
    public function finally(callable $callable): Promise;

    /** Borrow the loop until settled. Returns the value, or throws the reason. */
    public function wait(): mixed;

    /** The writing end: whoever does the work calls one of these, once. */
    public function resolve(mixed $value): void;
    public function reject(Throwable $reason): void;

    public function settled(): bool;
    public function fulfilled(): bool;
    public function rejected(): bool;
}