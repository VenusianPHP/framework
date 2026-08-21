<?php

namespace Voyager\Database\Instrument\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class UseInstrumentBuilder
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Voyager\Database\Instrument\Builder>  $builderClass
     */
    public function __construct(public string $builderClass)
    {
    }
}
