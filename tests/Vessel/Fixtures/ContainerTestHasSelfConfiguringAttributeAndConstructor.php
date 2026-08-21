<?php

namespace Tests\Vessel\Fixtures;

#[ContainerTestConfiguresClass(value: 'the-right-value')]
final class ContainerTestHasSelfConfiguringAttributeAndConstructor
{
    public function __construct(
        public string $value
    ) {
    }
}
