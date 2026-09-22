<?php

namespace Voyager\Workflows\Concerns;

use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Workflows\Runtimes\LoopRuntime;

trait ResolvesAsyncRuntime
{
    protected ?AsyncRuntime $runtime = null;

    public function usesRuntime(AsyncRuntime $runtime): static
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * A bare node gets a private loop, so a unit test needs no container.
     */
    public function runtime(): AsyncRuntime
    {
        return $this->runtime ??= LoopRuntime::isolated();
    }
}
