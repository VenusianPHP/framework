<?php

namespace Voyager\Workflows\Concerns;

use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Workflows\Runtimes\LoopRuntime;
use Voyager\Contracts\Vessel\DataBindingException;

trait ResolvesAsyncRuntime
{
    protected ?AsyncRuntime $runtime = null;

    public function usesRuntime(AsyncRuntime $runtime): static
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * The runtime a flow handed over; otherwise the app's, on the app's loop; otherwise, with no
     * app at all, the standalone loop, so a node runs in a unit test with no container.
     */
    public function runtime(): AsyncRuntime
    {
        return $this->runtime ??= self::appRuntime() ?? LoopRuntime::standalone();
    }

    private static function appRuntime(): ?AsyncRuntime
    {
        $app = ControlPanel::getInstance();

        try {
            return match (true) {
                $app->isBound(AsyncRuntimeManager::class) => $app->get(AsyncRuntimeManager::class)->driver(),
                $app->isBound(Loop::class) => new LoopRuntime($app->get(Loop::class)),
                default => null,
            };
        } catch (DataBindingException) {
            // The core alias names the loop before IOPools has registered one.
            return null;
        }
    }
}
