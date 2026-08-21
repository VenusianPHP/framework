<?php

namespace Tests\Vessel\Fixtures;

use Attribute;
use Voyager\Contracts\Vessel\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ContainerTestConfigValue implements ContextualAttribute
{
    public function __construct(
        public readonly string $key
    ) {
    }
}
