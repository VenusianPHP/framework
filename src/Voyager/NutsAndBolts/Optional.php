<?php

namespace Voyager\NutsAndBolts;

use Voyager\NutsAndBolts\DataObjects\Arr;

use ArrayAccess;
use ArrayObject;
use Voyager\NutsAndBolts\Concerns\Macroable;

class Optional implements ArrayAccess
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * The underlying object.
     *
     * @var mixed
     */
    protected mixed $value;

    /**
     * Create a new optional instance.
     *
     * @param  mixed  $value
     */
    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Dynamically access a property on the underlying object.
     *
     * @param string $offset
     * @return mixed
     */
    public function __get(string $offset)
    {
        if (is_object($this->value)) {
            return $this->value->{$offset} ?? null;
        }
    }

    /**
     * Dynamically check a property exists on the underlying object.
     *
     * @param  mixed  $name
     * @return bool
     */
    public function __isset($name)
    {
        if (is_object($this->value)) {
            return isset($this->value->{$name});
        }

        if (is_array($this->value) || $this->value instanceof ArrayObject) {
            return isset($this->value[$name]);
        }

        return false;
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return Arr::accessible($this->value) && Arr::exists($this->value, $offset);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return Arr::get($this->value, $offset);
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (Arr::accessible($this->value)) {
            $this->value[$offset] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (Arr::accessible($this->value)) {
            unset($this->value[$offset]);
        }
    }

    /**
     * Dynamically pass a method to the underlying object.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (is_object($this->value)) {
            return $this->value->{$method}(...$parameters);
        }
    }
}
