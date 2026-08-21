<?php

namespace Voyager\Contracts\Config;

interface Repository
{
    /**
     * Determine if the given configuration value exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key);

    /**
     * Get the specified configuration value.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(array|string $key, mixed $default = null);

    /**
     * Get every configuration item for the application.
     *
     * @return array
     */
    public function all();

    /**
     * Set a given configuration value.
     *
     * @param  array|string  $key
     * @param  mixed  $value
     * @return void
     */
    public function set(array|string $key, mixed $value = null);

    /**
     * Prepend a value onto an array configuration value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function prepend(string $key, mixed $value);

    /**
     * Push a value onto an array configuration value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function push(string $key, mixed $value);
}
