<?php

namespace Voyager\Database\Instrument\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class UseFactory
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Voyager\Database\Instrument\Factories\Factory>  $factoryClass
     */
    public function __construct(public string $factoryClass)
    {
    }
}
