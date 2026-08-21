<?php

namespace Voyager\System;

use Voyager\NutsAndBolts\Manager;

class MaintenanceModeManager extends Manager
{
    /**
     * Create an instance of the file based maintenance driver.
     *
     * @return \Voyager\System\FileBasedMaintenanceMode
     */
    protected function createFileDriver(): FileBasedMaintenanceMode
    {
        return new FileBasedMaintenanceMode();
    }

    /**
     * Create an instance of the cache based maintenance driver.
     *
     * @return \Voyager\System\CacheBasedMaintenanceMode
     *
     * @throws \Voyager\Contracts\Vessel\BindingResolutionException
     */
    protected function createCacheDriver(): CacheBasedMaintenanceMode
    {
        return new CacheBasedMaintenanceMode(
            $this->vessel->make('cache'),
            $this->config->get('app.maintenance.store') ?: $this->config->get('cache.default'),
            'illuminate:foundation:down'
        );
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('app.maintenance.driver', 'file');
    }
}
