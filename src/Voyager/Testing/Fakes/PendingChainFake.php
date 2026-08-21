<?php

namespace Voyager\Testing\Fakes;

use Closure;
use Voyager\System\Bus\PendingChain;
use Voyager\Queue\CallQueuedClosure;

class PendingChainFake extends PendingChain
{
    /**
     * The fake bus instance.
     *
     * @var \Voyager\Testing\Fakes\BusFake
     */
    protected $bus;

    /**
     * Create a new pending chain instance.
     *
     * @param  \Voyager\Testing\Fakes\BusFake  $bus
     * @param  mixed  $job
     * @param  array  $chain
     */
    public function __construct(BusFake $bus, array $job, $chain)
    {
        $this->bus = $bus;
        $this->job = $job;
        $this->chain = $chain;
    }

    /**
     * Dispatch the job with the given arguments.
     *
     * @return \Voyager\System\Bus\PendingDispatch
     */
    public function dispatch(): \Voyager\System\Bus\PendingDispatch
    {
        if (is_string($this->job)) {
            $firstJob = new $this->job(...func_get_args());
        } elseif ($this->job instanceof Closure) {
            $firstJob = CallQueuedClosure::create($this->job);
        } else {
            $firstJob = $this->job;
        }

        $firstJob->allOnConnection($this->connection);
        $firstJob->allOnQueue($this->queue);
        $firstJob->chain($this->chain);
        $firstJob->delay($this->delay);
        $firstJob->chainCatchCallbacks = $this->catchCallbacks();

        return $this->bus->dispatch($firstJob);
    }
}
