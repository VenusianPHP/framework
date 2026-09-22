<?php

namespace Voyager\NutsAndBolts;

use Voyager\Contracts\Config\Repository;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\DataObjects\Str;

use Closure;
use InvalidArgumentException;
use RuntimeException;

abstract class MultipleInstanceManager
{
    /**
     * The application instance.
     *
     * @var FrameworkCore
     */
    protected FrameworkCore $app;

    /**
     * The configuration repository instance.
     *
     * @var Repository
     */
    protected Repository $config;

    /**
     * The array of resolved instances.
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * The registered custom instance creators.
     *
     * @var array
     */
    protected array $custom_creators = [];

    /**
     * The key name of the "driver" equivalent configuration option.
     *
     * @var string
     */
    protected string $driver_key = 'driver';

    /**
     * Create a new manager instance.
     *
     * @param FrameworkCore $app
     */
    public function __construct(FrameworkCore $app)
    {
        $this->app = $app;
        $this->config = $app->make('config');
    }

    /**
     * Get the default instance name.
     *
     * @return string
     */
    abstract public function getDefaultInstance(): string;

    /**
     * Set the default instance name.
     *
     * @param string $name
     * @return void
     */
    abstract public function setDefaultInstance(string $name): void;

    /**
     * Get the instance specific configuration.
     *
     * @param string $name
     * @return array
     */
    abstract public function getInstanceConfig(string $name): array;

    /**
     * Get an instance by name.
     *
     * @param string|null $name
     * @return mixed
     */
    public function instance(?string $name = null): mixed
    {
        $name = $name ?: $this->getDefaultInstance();

        return $this->instances[$name] = $this->get($name);
    }

    /**
     * Attempt to get an instance from the local cache.
     *
     * @param string $name
     * @return mixed
     */
    protected function get(string $name): mixed
    {
        return $this->instances[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given instance.
     *
     * @param string $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function resolve(string $name): mixed
    {
        $config = $this->getInstanceConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Instance [{$name}] is not defined.");
        }

        if (! array_key_exists($this->driver_key, $config)) {
            throw new RuntimeException("Instance [{$name}] does not specify a {$this->driver_key}.");
        }

        $driverName = $config[$this->driver_key];

        if (isset($this->custom_creators[$driverName])) {
            return $this->callCustomCreator($config);
        } else {
            $createMethod = 'create'.ucfirst($driverName).ucfirst($this->driver_key);

            if (method_exists($this, $createMethod)) {
                return $this->{$createMethod}($config);
            }

            $createMethod = 'create'.Str::studly($driverName).ucfirst($this->driver_key);

            if (method_exists($this, $createMethod)) {
                return $this->{$createMethod}($config);
            }

            throw new InvalidArgumentException("Instance {$this->driver_key} [{$config[$this->driver_key]}] is not supported.");
        }
    }

    /**
     * Call a custom instance creator.
     *
     * @param  array  $config
     * @return mixed
     */
    protected function callCustomCreator(array $config): mixed
    {
        return $this->custom_creators[$config[$this->driver_key]]($this->app, $config);
    }

    /**
     * Unset the given instances.
     *
     * @param array|string|null $name
     * @return $this
     */
    public function forgetInstance(array|string|null $name = null): static
    {
        $name ??= $this->getDefaultInstance();

        foreach ((array) $name as $instanceName) {
            if (isset($this->instances[$instanceName])) {
                unset($this->instances[$instanceName]);
            }
        }

        return $this;
    }

    /**
     * Disconnect the given instance and remove from local cache.
     *
     * @param string|null $name
     * @return void
     */
    public function purge(?string $name = null): void
    {
        $name ??= $this->getDefaultInstance();

        unset($this->instances[$name]);
    }

    /**
     * Register a custom instance creator Closure.
     *
     * @param string $name
     * @param  \Closure  $callback
     *
     * @param-closure-this  $this  $callback
     *
     * @return $this
     */
    public function extend(string $name, Closure $callback): static
    {
        $this->custom_creators[$name] = $callback->bindTo($this, $this);

        return $this;
    }

    /**
     * Set the application instance used by the manager.
     *
     * @param FrameworkCore $app
     * @return static
     */
    public function setApplication(FrameworkCore $app): static
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Dynamically call the default instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->instance()->$method(...$parameters);
    }
}
