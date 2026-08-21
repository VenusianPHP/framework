<?php

namespace Voyager\Database\Instrument\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CollectedBy
{
    /**
     * Create a new attribute instance.
     *
     * @param  class-string<\Voyager\Database\Instrument\Collection<*, *>>  $collectionClass
     */
    public function __construct(public string $collectionClass)
    {
    }
}
