<?php

namespace Voyager\Workflows\Runtimes;

use Closure;
use Throwable;
use Voyager\IOPools\EventLoop;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\Workflows\AsyncRuntime;

/**
 * The async runtime, on the event loop. await() borrows on the main stack and suspends inside a
 * loop fiber, so a node graph run from a command finishes on its own, and one run under async()
 * interleaves with everything else the loop watches. Nothing here sleeps.
 */
class LoopRuntime implements AsyncRuntime
{
    public function __construct(private readonly Loop $loop) {}

    /** A runtime on a loop nobody else shares: scripts, tests, a node run bare. */
    public static function isolated(): static
    {
        return new static(new EventLoop);
    }

    public function loop(): Loop
    {
        return $this->loop;
    }

    public function async(Closure $work): Promise
    {
        // a body may return a promise; the task settles with what it holds, not the promise itself
        return $this->loop->async(fn () => $this->await($work()));
    }

    public function resolve(mixed $value): Promise
    {
        if ($value instanceof Promise) {
            return $value;
        }

        $promise = $this->loop->promise();
        $promise->resolve($value);

        return $promise;
    }

    public function await(mixed $value): mixed
    {
        return $this->loop->await($value);
    }

    public function delay(float $seconds): Promise
    {
        $promise = $this->loop->promise();

        $this->loop->at(max(0.0, $seconds), fn () => $promise->resolve(null));

        return $promise;
    }

    public function all(iterable $work, ?int $concurrency = null): Promise
    {
        $entries = is_array($work) ? $work : iterator_to_array($work);
        $done = $this->loop->promise();

        if (empty($entries)) {
            $done->resolve([]);
            return $done;
        }

        $queue = array_keys($entries);
        $results = array_fill_keys($queue, null);
        $pending = count($entries);
        $running = 0;
        $failure = null;
        $limit = is_null($concurrency) || $concurrency < 1 ? PHP_INT_MAX : $concurrency;

        $settle = function (string|int $key, ?Throwable $e, mixed $value) use (&$results, &$pending, &$running, &$failure, &$start, $done) {
            $results[$key] = $value;
            $failure ??= $e;                         // first failure wins; the rest still run
            $running--;

            if (--$pending === 0) {
                is_null($failure) ? $done->resolve($results) : $done->reject($failure);
                return;
            }

            $start();
        };

        $start = function () use (&$queue, &$running, $limit, $entries, $settle) {
            while ($queue && $running < $limit)
            {
                $key = array_shift($queue);
                $running++;

                $entry = $entries[$key];

                ($entry instanceof Closure ? $this->async($entry) : $this->resolve($entry))
                    ->then(fn ($v) => $settle($key, null, $v))
                    ->error(fn (Throwable $e) => $settle($key, $e, null));
            }
        };

        $start();

        return $done;
    }
}
