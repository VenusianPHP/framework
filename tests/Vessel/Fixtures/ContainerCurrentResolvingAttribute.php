<?php

namespace Tests\Vessel\Fixtures;

use Attribute;

use Voyager\Contracts\Vessel\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContainerCurrentResolvingAttribute implements ContextualAttribute
{
    public function resolve()
    {
    }
}
