<?php

namespace Venusian\Tests\Pipeline\Fixtures;

use Closure;

/** Appends to the passable: "!" by default, or what the pipe string names after the colon. */
final class AppendPipe
{
    public function handle(string $passable, Closure $next, string $suffix = '!'): mixed
    {
        return $next($passable.$suffix);
    }

    public function differently(string $passable, Closure $next): mixed
    {
        return $next($passable.'?');
    }
}
