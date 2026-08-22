<?php

namespace Voyager\Workflows\Concerns;

use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Workflows\Runtimes\SyncRuntime;

trait ResolvesAsyncRuntime
{
    protected ?AsyncRuntime $runtime = null;

    public function usesRuntime(AsyncRuntime $runtime): static
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * Falling back to the sync runtime keeps a node usable with no container
     * and no extra packages present.
     */
    public function runtime(): AsyncRuntime
    {
        return $this->runtime ??= new SyncRuntime;
    }
}
