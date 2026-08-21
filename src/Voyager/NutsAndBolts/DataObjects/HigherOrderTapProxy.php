<?php

namespace Voyager\NutsAndBolts\DataObjects;

class HigherOrderTapProxy
{
    /**
     * The target being tapped.
     *
     * @var mixed
     */
    public mixed $target;

    /**
     * Create a new tap proxy instance.
     *
     * @param  mixed  $target
     */
    public function __construct(mixed $target)
    {
        $this->target = $target;
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        $this->target->{$method}(...$parameters);

        return $this->target;
    }
}