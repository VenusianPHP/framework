<?php

namespace Voyager\Workflows\Runtimes;

use Closure;
use Throwable;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
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
    /** The standalone loop's pace, the same as the app loop's default. */
    private const int PACE_NS = 16_000_000;

    private static ?self $standalone = null;

    public function __construct(private readonly Loop $loop) {}

    /**
     * For code running with no app: a node in a unit test, a script. One loop per process, built
     * from IOPools' own parts (the best waiter backend this machine has, and the Guzzle promise
     * engine), since everything async has to run on the one loop that can turn it.
     */
    public static function standalone(): self
    {
        if (is_null(self::$standalone)) {
            $registry = new ResourceRegistry();
            $backend = match (true) {
                extension_loaded('epoll') => new EpollWaiterBackend(),
                extension_loaded('kqueue') => new KqueueWaiterBackend(),
                default => new StreamSelectWaiterBackend(),
            };

            self::$standalone = new self(new EventLoop(
                $registry, new LoopWaiter($registry, $backend, self::PACE_NS), new GuzzlePromiseEngine(),
            ));
        }

        return self::$standalone;
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
