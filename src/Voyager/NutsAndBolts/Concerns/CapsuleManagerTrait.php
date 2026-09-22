<?php

namespace Voyager\NutsAndBolts\Concerns;

use Voyager\Contracts\Vessel\TheServiceContainer;
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
     * @var \Voyager\Contracts\Vessel\TheServiceContainer
     */
    protected ?TheServiceContainer $vessel = null;

    /**
     * Setup the IoC container instance.
     *
     * @param  \Voyager\Contracts\Vessel\TheServiceContainer  $vessel
     * @return void
     */
    protected function setupContainer(TheServiceContainer $vessel): void
    {
        $this->vessel = $vessel;

        if (! $this->vessel->isBound('config')) {
            $this->vessel->registerInstance('config', new Fluent);
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
     * @return \Voyager\Contracts\Vessel\TheServiceContainer
     */
    public function getContainer(): TheServiceContainer
    {
        return $this->vessel;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \Voyager\Contracts\Vessel\TheServiceContainer  $vessel
     * @return void
     */
    public function setContainer(TheServiceContainer $vessel): void
    {
        $this->vessel = $vessel;
    }
}
