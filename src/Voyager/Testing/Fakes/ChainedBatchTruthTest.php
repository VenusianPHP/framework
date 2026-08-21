<?php

namespace Voyager\Testing\Fakes;

use Closure;

class ChainedBatchTruthTest
{
    /**
     * The underlying truth test.
     *
     * @var \Closure(\Voyager\Bus\PendingBatch): bool
     */
    protected $callback;

    /**
     * Create a new truth test instance.
     *
     * @param  \Closure(\Voyager\Bus\PendingBatch): bool  $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Invoke the truth test with the given pending batch.
     *
     * @param  \Voyager\Bus\PendingBatch  $pendingBatch
     * @return bool
     */
    public function __invoke($pendingBatch)
    {
        return call_user_func($this->callback, $pendingBatch);
    }
}
