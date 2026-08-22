<?php

namespace Voyager\Workflows\Runtimes;

use Closure;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Runtimes\Concerns\SettlesGroups;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\resolve;

/**
 * Overlaps work on the ReactPHP event loop.
 *
 * Needs the "react/async" package. The loop is never started explicitly:
 * awaiting drives it, which is what lets a workflow run inside an application
 * that owns the loop already.
 */
class ReactRuntime implements AsyncRuntime
{
    use SettlesGroups;

    public function __construct()
    {
        if (!function_exists('React\Async\async')) {
            throw new WorkflowRuntimeException(
                'Please install the "react/async" Composer package in order to utilize the "react" runtime.'
            );
        }
    }

    public function async(Closure $work): Awaitable
    {
        return new ReactAwaitable(async(fn () => $this->await($work()))());
    }

    public function resolve(mixed $value): Awaitable
    {
        return $value instanceof Awaitable ? $value : new ReactAwaitable(resolve($value));
    }

    public function await(mixed $value): mixed
    {
        if (!$value instanceof Awaitable) {
            return $value;
        }

        if (!$value instanceof ReactAwaitable) {
            throw new WorkflowRuntimeException(
                'The react runtime cannot await ['.get_class($value).'], which belongs to another runtime.'
            );
        }

        return await($value->promise());
    }

    public function delay(float $seconds): Awaitable
    {
        if ($seconds <= 0) {
            return new ReactAwaitable(resolve(null));
        }

        $deferred = new Deferred;

        Loop::addTimer($seconds, static fn () => $deferred->resolve(null));

        return new ReactAwaitable($deferred->promise());
    }
}
