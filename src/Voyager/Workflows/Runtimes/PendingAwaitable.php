<?php

namespace Voyager\Workflows\Runtimes;

use Throwable;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;

/**
 * An awaitable whose outcome arrives later, used by the fiber runtime.
 */
final class PendingAwaitable implements Awaitable
{
    private bool $settled = false;

    private bool $fulfilled = false;

    private mixed $value = null;

    private ?Throwable $reason = null;

    /**
     * @var array<int, callable(): void>
     */
    private array $callbacks = [];

    public function isSettled(): bool
    {
        return $this->settled;
    }

    public function fulfill(mixed $value): void
    {
        $this->settle(true, $value, null);
    }

    public function reject(Throwable $reason): void
    {
        $this->settle(false, null, $reason);
    }

    /**
     * Fulfill with a plain value, or follow the outcome of another awaitable.
     */
    public function settleWith(mixed $value): void
    {
        if (!$value instanceof Awaitable) {
            $this->fulfill($value);

            return;
        }

        $value->then(
            fn (mixed $resolved) => $this->fulfill($resolved),
            fn (Throwable $reason) => $this->reject($reason),
        );
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Awaitable
    {
        $next = new self;

        $handler = function () use ($onFulfilled, $onRejected, $next) {
            try {
                if ($this->fulfilled) {
                    $next->settleWith(is_null($onFulfilled) ? $this->value : $onFulfilled($this->value));
                } elseif (is_null($onRejected)) {
                    $next->reject($this->reason);
                } else {
                    $next->settleWith($onRejected($this->reason));
                }
            } catch (Throwable $e) {
                $next->reject($e);
            }
        };

        if ($this->settled) {
            $handler();
        } else {
            $this->callbacks[] = $handler;
        }

        return $next;
    }

    /**
     * @throws Throwable The rejection reason, if this awaitable failed.
     */
    public function unwrap(): mixed
    {
        if (!$this->settled) {
            throw new WorkflowRuntimeException('Cannot unwrap an awaitable that has not settled.');
        }

        if ($this->fulfilled) {
            return $this->value;
        }

        throw $this->reason;
    }

    private function settle(bool $fulfilled, mixed $value, ?Throwable $reason): void
    {
        if ($this->settled) {
            return;
        }

        $this->settled = true;
        $this->fulfilled = $fulfilled;
        $this->value = $value;
        $this->reason = $reason;

        $callbacks = $this->callbacks;
        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            $callback();
        }
    }
}
