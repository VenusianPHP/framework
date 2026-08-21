<?php

namespace Voyager\NutsAndBolts\Concerns;

use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\Fluent;

trait CapsuleManagerTrait
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static ?object $instance = null;

    /**
     * The container instance.
     *
     * @var \Voyager\Contracts\Vessel\Vessel
     */
    protected ?Vessel $vessel = null;

    /**
     * Setup the IoC container instance.
     *
     * @param  \Voyager\Contracts\Vessel\Vessel  $vessel
     * @return void
     */
    protected function setupContainer(Vessel $vessel): void
    {
        $this->vessel = $vessel;

        if (! $this->vessel->bound('config')) {
            $this->vessel->instance('config', new Fluent);
        }
    }

    /**
     * Make this capsule instance available globally.
     *
     * @return void
     */
    public function setAsGlobal(): void
    {
        static::$instance = $this;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \Voyager\Contracts\Vessel\Vessel
     */
    public function getContainer(): Vessel
    {
        return $this->vessel;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \Voyager\Contracts\Vessel\Vessel  $vessel
     * @return void
     */
    public function setContainer(Vessel $vessel): void
    {
        $this->vessel = $vessel;
    }
}
