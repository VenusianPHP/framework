<?php

namespace Voyager\NutsAndBolts;

use Voyager\NutsAndBolts\DataObjects\Str;

use Closure;
use Voyager\Contracts\Vessel\TheServiceContainer;
use InvalidArgumentException;

abstract class Manager
{
    /**
     * The container instance.
     *
     * @var TheServiceContainer
     */
    protected TheServiceContainer $vessel;

    /**
     * The configuration repository instance.
     *
     * @var \Voyager\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param TheServiceContainer $vessel
     */
    public function __construct(TheServiceContainer $vessel)
    {
        $this->vessel = $vessel;
        $this->config = $vessel->make('config');
    }

    /**
     * Get the default driver name.
     *
     * @return string|null
     */
    abstract public function getDefaultDriver(): ?string;

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver(?string $driver = null): mixed
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        return $this->drivers[$driver] ??= $this->createDriver($driver);
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver(string $driver): mixed
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create'.Str::studly($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return mixed
     */
    protected function callCustomCreator(string $driver): mixed
    {
        return $this->customCreators[$driver]($this->vessel);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get every created "driver".
     *
     * @return array
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return TheServiceContainer
     */
    public function getContainer(): TheServiceContainer
    {
        return $this->vessel;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param TheServiceContainer $vessel
     * @return $this
     */
    public function setContainer(TheServiceContainer $vessel): static
    {
        $this->vessel = $vessel;

        return $this;
    }

    /**
     * Forget every resolved driver instance.
     *
     * @return $this
     */
    public function forgetDrivers(): static
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
