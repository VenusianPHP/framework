<?php

namespace Tests\Vessel\Fixtures;

use Attribute;
use Voyager\Contracts\Vessel\ContextualAttribute;
use Voyager\Vessel\Vessel;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ContainerTestConfigValueWithResolveAndAfter implements ContextualAttribute
{
    public function resolve(self $attribute, Vessel $vessel): object
    {
        return (object) ['name' => 'Taylor'];
    }

    public function after(self $attribute, object $value, Vessel $vessel): void
    {
        $value->role = 'Developer';
    }
}
