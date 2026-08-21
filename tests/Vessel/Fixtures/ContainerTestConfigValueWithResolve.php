<?php

namespace Tests\Vessel\Fixtures;

use Attribute;
use Voyager\Contracts\Vessel\ContextualAttribute;
use Voyager\Vessel\Vessel;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ContainerTestConfigValueWithResolve implements ContextualAttribute
{
    public function __construct(
        public readonly string $key
    ) {
    }

    public function resolve(self $attribute, Vessel $vessel): string
    {
        return $vessel->make('config')->get($attribute->key);
    }
}
