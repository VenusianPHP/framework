<?php

namespace Tests\Vessel\Fixtures;

function containerTestInject(ContainerCallConcreteStub $stub, $default = 'taylor')
{
    return func_get_args();
}
