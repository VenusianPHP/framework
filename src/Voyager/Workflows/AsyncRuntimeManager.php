<?php

namespace Voyager\Workflows;

use BackedEnum;
use UnitEnum;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\NutsAndBolts\Manager;
use Voyager\Workflows\Runtimes\FiberRuntime;
use Voyager\Workflows\Runtimes\ReactRuntime;
use Voyager\Workflows\Runtimes\SyncRuntime;

/**
 * @mixin \Voyager\Contracts\Workflows\AsyncRuntime
 */
class AsyncRuntimeManager extends Manager
{
    public function driver(UnitEnum|string|null $driver = null): AsyncRuntime
    {
        return parent::driver(match (true) {
            $driver instanceof BackedEnum => $driver->value,
            $driver instanceof UnitEnum => $driver->name,
            default => $driver,
        });
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('workflows.runtime', AsyncRuntimeDriver::SYNC->value);
    }

    protected function createSyncDriver(): AsyncRuntime
    {
        return new SyncRuntime;
    }

    protected function createFiberDriver(): AsyncRuntime
    {
        return new FiberRuntime;
    }

    protected function createReactDriver(): AsyncRuntime
    {
        return new ReactRuntime;
    }
}
