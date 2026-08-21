<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Closure;
use Voyager\MagicAliases\MagicAlias;
use UnitEnum;

/**
 * @method static \Voyager\Pipeline\Pipeline send(mixed $passable)
 * @method static \Voyager\Pipeline\Pipeline through(mixed $pipes)
 * @method static \Voyager\Pipeline\Pipeline pipe(mixed $pipes)
 * @method static \Voyager\Pipeline\Pipeline via(string $method)
 * @method static mixed then(Closure $destination)
 * @method static mixed thenReturn()
 * @method static \Voyager\Pipeline\Pipeline finally(Closure $callback)
 * @method static \Voyager\Pipeline\Pipeline withinTransaction(string|null|UnitEnum|false $withinTransaction = null)
 * @method static \Voyager\Pipeline\Pipeline setContainer(\Voyager\Vessel\Contracts\WireframeServiceContainer $container)
 * @method static \Voyager\Pipeline\Pipeline|mixed when(Closure|mixed|null $value = null, callable|null $callback = null, callable|null $default = null)
 * @method static \Voyager\Pipeline\Pipeline|mixed unless(Closure|mixed|null $value = null, callable|null $callback = null, callable|null $default = null)
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 *
 * @see \Voyager\Pipeline\Pipeline
 */
class Pipeline extends MagicAlias
{
    /**
     * Indicates if the resolved instance should be cached.
     *
     * Fresh Pipeline per Magic Alias call (matches Venusian Magic Alias).
     *
     * @var bool
     */
    protected static bool $cached = false;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'pipeline';
    }
}
