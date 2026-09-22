<?php

namespace Voyager\Workflows;

use BackedEnum;
use UnitEnum;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\NutsAndBolts\Manager;
use Voyager\Workflows\Runtimes\LoopRuntime;

/**
 * @mixin \Voyager\Contracts\Workflows\AsyncRuntime
 */
class AsyncRuntimeManager extends Manager
{
    /**
     * @param  UnitEnum|string|null  $driver
     */
    public function driver($driver = null): AsyncRuntime
    {
        return parent::driver(match (true) {
            $driver instanceof BackedEnum => $driver->value,
            $driver instanceof UnitEnum => $driver->name,
            default => $driver,
        });
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('workflows.runtime', AsyncRuntimeDriver::LOOP->value);
    }

    protected function createLoopDriver(): AsyncRuntime
    {
        return new LoopRuntime($this->vessel->make(Loop::class));
    }

    protected function createIsolatedDriver(): AsyncRuntime
    {
        return LoopRuntime::isolated();
    }
}
