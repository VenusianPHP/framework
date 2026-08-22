<?php

namespace Voyager\Workflows\Runtimes;

use React\Promise\PromiseInterface;
use Voyager\Contracts\Workflows\Awaitable;

/**
 * Wraps a ReactPHP promise so the rest of the package never names one.
 */
final class ReactAwaitable implements Awaitable
{
    public function __construct(private readonly PromiseInterface $promise) {}

    public function promise(): PromiseInterface
    {
        return $this->promise;
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Awaitable
    {
        $adapt = fn (?callable $handler) => is_null($handler)
            ? null
            : fn (mixed $argument) => self::unwrap($handler($argument));

        return new self($this->promise->then($adapt($onFulfilled), $adapt($onRejected)));
    }

    private static function unwrap(mixed $value): mixed
    {
        return $value instanceof self ? $value->promise() : $value;
    }
}
