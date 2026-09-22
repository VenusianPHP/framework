<?php

namespace Voyager\Contracts\Vessel;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface as ServiceContainerContract;
use ReflectionException;

interface TheServiceContainer extends ServiceContainerContract
{
    /**
     * {@inheritdoc}
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $id
     * @return ($id is class-string<TClass> ? TClass : mixed)
     */
    public function get(string $id): mixed;

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function isBound(string $abstract): bool;

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param string $abstract
     * @return bool
     */
    public function isResolved(string $abstract): bool;

    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param string|class-string<TClass> $abstract
     * @param  array  $parameters
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws DataBindingException
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Register a binding with the container.
     *
     * @param callable|string $abstract
     * @param callable|string|null $concrete
     * @param bool $shared
     * @return void
     */
    public function bind(callable|string $abstract, callable|string|null $concrete = null, bool $shared = false): void;

    /**
     * Call the given Closure / class method and inject its dependencies.
     *
     * @param callable|string $callback
     * @param  array  $parameters
     * @param string|null $default_method
     * @return mixed
     */
    public function call(callable|string $callback, array $parameters = [], ?string $default_method = null): mixed;

    /**
     * Add a contextual binding to the container.
     *
     * @param string $concrete
     * @param callable|string $abstract
     * @param callable|string $implementation
     * @return void
     */
    public function addContextualBinding(string $concrete, callable|string $abstract, callable|string $implementation): void;

    /**
     * Define a contextual binding.
     *
     * @param array|string $concrete
     * @return ContextualBindingBuilder
     */
    public function when(array|string $concrete): ContextualBindingBuilder;


    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     *
     * @throws LogicException
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Register a scoped binding in the container.
     *
     * @param callable|string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    public function scoped(callable|string $abstract, callable|string|null $concrete = null): void;

    /**
     * Register a new resolving callback.
     *
     * @param callable|string $abstract
     * @param  callable|null  $callback
     * @return void
     */
    public function resolving(callable|string $abstract, ?callable $callback = null): void;

    /**
     * Register a shared binding in the container.
     *
     * @param callable|string $abstract
     * @param callable|string|null $concrete
     * @return void
     * @throws ReflectionException
     */
    public function registerSingleton(callable|string $abstract, callable|string|null $concrete = null): void;

}