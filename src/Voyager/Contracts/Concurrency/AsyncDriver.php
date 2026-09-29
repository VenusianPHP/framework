<?php

namespace Voyager\Contracts\Concurrency;

use Closure;
use Voyager\Contracts\IOPools\Promise;

/**
 * A driver that can also run tasks without blocking the loop.
 */
interface AsyncDriver extends Driver
{
    /**
     * Start the given tasks and return straight away. Once every task has settled, their results
     * and failures arrive together as ConcurrencyResults mail named "concurrency:{$name}".
     *
     * @param  Closure|array<array-key, Closure>  $tasks
     * @return Promise the results keyed as the tasks were, or the first failure in key order
     */
    public function async(Closure|array $tasks, string $name = 'default'): Promise;
}
