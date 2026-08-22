<?php

namespace Voyager\Workflows\Runtimes;

use Throwable;
use Voyager\Contracts\Workflows\Awaitable;

/**
 * An awaitable that already holds its outcome.
 */
final class SettledAwaitable implements Awaitable
{
    private function __construct(
        private readonly bool $fulfilled,
        private readonly mixed $value,
        private readonly ?Throwable $reason,
    ) {}

    public static function fulfilled(mixed $value): self
    {
        return new self(true, $value, null);
    }

    public static function rejected(Throwable $reason): self
    {
        return new self(false, null, $reason);
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Awaitable
    {
        try {
            if ($this->fulfilled) {
                return is_null($onFulfilled) ? $this : self::adopt($onFulfilled($this->value));
            }

            return is_null($onRejected) ? $this : self::adopt($onRejected($this->reason));
        } catch (Throwable $e) {
            return self::rejected($e);
        }
    }

    /**
     * @throws Throwable The rejection reason, if this awaitable failed.
     */
    public function unwrap(): mixed
    {
        if ($this->fulfilled) {
            return $this->value;
        }

        throw $this->reason;
    }

    private static function adopt(mixed $value): Awaitable
    {
        return $value instanceof Awaitable ? $value : self::fulfilled($value);
    }
}
