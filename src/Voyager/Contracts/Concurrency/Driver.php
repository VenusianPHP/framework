<?php

namespace Voyager\Contracts\Concurrency;

use Closure;

interface Driver
{
    /**
     * Run the given tasks concurrently and return their results, keyed as the tasks were.
     *
     * @param  Closure|array<array-key, Closure>  $tasks
     * @return array<array-key, mixed>
     */
    public function run(Closure|array $tasks): array;
}
