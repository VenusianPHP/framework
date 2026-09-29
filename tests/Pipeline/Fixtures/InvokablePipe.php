<?php

namespace Venusian\Tests\Pipeline\Fixtures;

use Closure;

/** A pipe with no handle(): the pipeline invokes it. */
final class InvokablePipe
{
    public function __invoke(string $passable, Closure $next): mixed
    {
        return $next("[{$passable}]");
    }
}
