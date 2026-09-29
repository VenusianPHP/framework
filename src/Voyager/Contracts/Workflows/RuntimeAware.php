<?php

namespace Voyager\Contracts\Workflows;

/**
 * Implemented by graph members whose async runtime may be supplied from above.
 *
 * A flow hands its own runtime to every member it orchestrates so a graph runs
 * on one runtime without each node resolving one for itself.
 */
interface RuntimeAware
{
    /**
     * Use the given runtime for asynchronous work.
     */
    public function usesRuntime(AsyncRuntime $runtime): static;

    /**
     * Get the runtime driving asynchronous work.
     */
    public function runtime(): AsyncRuntime;
}
