<?php

namespace Voyager\Workflows\Runtimes;

use Closure;
use Throwable;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Runtimes\Concerns\SettlesGroups;

/**
 * Runs asynchronous work immediately on the calling stack.
 *
 * Nothing overlaps, which makes this runtime the deterministic default: async
 * nodes and flows behave identically here with no extra packages installed.
 */
class SyncRuntime implements AsyncRuntime
{
    use SettlesGroups;

    public function async(Closure $work): Awaitable
    {
        try {
            return SettledAwaitable::fulfilled($this->await($work()));
        } catch (Throwable $e) {
            return SettledAwaitable::rejected($e);
        }
    }

    public function resolve(mixed $value): Awaitable
    {
        return $value instanceof Awaitable ? $value : SettledAwaitable::fulfilled($value);
    }

    public function await(mixed $value): mixed
    {
        if (!$value instanceof Awaitable) {
            return $value;
        }

        if ($value instanceof SettledAwaitable) {
            return $value->unwrap();
        }

        throw new WorkflowRuntimeException(
            'The sync runtime cannot await ['.get_class($value).'], which belongs to another runtime.'
        );
    }

    public function delay(float $seconds): Awaitable
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }

        return SettledAwaitable::fulfilled(null);
    }
}
