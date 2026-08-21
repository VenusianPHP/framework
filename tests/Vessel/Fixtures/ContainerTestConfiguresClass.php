<?php

namespace Tests\Vessel\Fixtures;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ContainerTestConfiguresClass
{
    public function __construct(
        public readonly string $value
    ) {
    }
}
