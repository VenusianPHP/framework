<?php

namespace Voyager\Contracts\Vessel;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Voyager\Contracts\System\VenusianFrameworkException;

class CircularDependencyException extends VenusianFrameworkException implements ContainerExceptionInterface
{
    //
}
