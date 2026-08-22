<?php

namespace Voyager\Workflows;

use Voyager\Contracts\Workflows\AsyncRuntime;

/**
 * Maps execAsync() over the items returned by prepAsync(), all in flight.
 *
 * Results keep the key order of the items rather than completion order. Every
 * item settles before failures surface, so a rejection does not cancel work
 * already started on its siblings.
 */
class AsyncParallelBatchNode extends AsyncNode
{
    /**
     * @param int $maxRetries Maximum number of execution attempts per item (default: 1)
     * @param int $wait Seconds to wait between retries (default: 0)
     * @param int|null $concurrency Maximum items in flight, or null for no limit
     * @param AsyncRuntime|null $runtime Runtime to use when not supplied by a flow
     */
    public function __construct(
        int $maxRetries = 1,
        int $wait = 0,
        protected ?int $concurrency = null,
        ?AsyncRuntime $runtime = null,
    ) {
        parent::__construct($maxRetries, $wait, $runtime);
    }

    /**
     * @param mixed $items The resolved items from prepAsync()
     * @throws \Throwable
     */
    public function _execAsync(mixed $items): mixed
    {
        $runtime = $this->runtime();
        $tasks = [];

        foreach ($items ?? [] as $key => $item) {
            $tasks[$key] = fn () => parent::_execAsync($item);
        }

        return $runtime->await($runtime->all($tasks, $this->concurrency));
    }
}
