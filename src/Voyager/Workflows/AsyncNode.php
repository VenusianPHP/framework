<?php

namespace Voyager\Workflows;

use Voyager\Contracts\Workflows\AsyncRunnable;
use Voyager\Contracts\Workflows\AsyncRuntime;

class AsyncNode extends BaseNode implements AsyncRunnable
{
    use AsyncWorkflowLogic;

    /**
     * @param int $maxRetries Maximum number of execution attempts (default: 1)
     * @param int $wait Seconds to wait between retries (default: 0)
     * @param AsyncRuntime|null $runtime Runtime to use when not supplied by a flow
     */
    public function __construct(int $maxRetries = 1, int $wait = 0, ?AsyncRuntime $runtime = null)
    {
        $this->maxRetries = $maxRetries;
        $this->waitSeconds = $wait;
        $this->runtime = $runtime;
    }
}
