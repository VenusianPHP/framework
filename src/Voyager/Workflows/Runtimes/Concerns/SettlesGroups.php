<?php

namespace Voyager\Workflows\Runtimes\Concerns;

use Closure;
use Throwable;
use Voyager\Contracts\Workflows\Awaitable;

/**
 * Shared group semantics for runtimes: start everything, let every entry
 * settle, then surface the first rejection in key order.
 */
trait SettlesGroups
{
    public function all(iterable $awaitables, ?int $concurrency = null): Awaitable
    {
        return $this->async(function () use ($awaitables, $concurrency) {
            $entries = [];

            foreach ($awaitables as $key => $entry) {
                $entries[$key] = $entry;
            }

            $size = !is_null($concurrency) && $concurrency > 0
                ? $concurrency
                : max(count($entries), 1);

            $results = [];
            $failure = null;

            foreach (array_chunk($entries, $size, true) as $chunk) {
                $pending = [];

                foreach ($chunk as $key => $entry) {
                    $pending[$key] = $entry instanceof Closure ? $this->async($entry) : $entry;
                }

                foreach ($pending as $key => $entry) {
                    try {
                        $results[$key] = $this->await($entry);
                    } catch (Throwable $e) {
                        $results[$key] = null;
                        $failure ??= $e;
                    }
                }
            }

            if (!is_null($failure)) {
                throw $failure;
            }

            return $results;
        });
    }
}
