<?php

namespace Venusian\Tests\Vessel\Fixtures;

final class ContainerTestHasConfigValueWithResolvePropertyAndAfterCallback
{
    public function __construct(
        #[ContainerTestConfigValueWithResolveAndAfter]
        public object $person
    ) {
    }
}
