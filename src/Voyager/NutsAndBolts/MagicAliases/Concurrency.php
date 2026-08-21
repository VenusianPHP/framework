<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\Concurrency\ConcurrencyManager;
use Voyager\MagicAliases\MagicAlias;

/**
 * @method static mixed driver(\UnitEnum|string|null $name = null)
 * @method static array run(\Closure|array $tasks, \Carbon\CarbonInterval|int|null $timeout = null)
 * @method static \Voyager\NutsAndBolts\Defer\DeferredCallback defer(\Closure|array $tasks)
 * @method static string getDefaultInstance()
 * @method static void setDefaultInstance(\UnitEnum|string $name)
 * @method static \Voyager\Concurrency\ConcurrencyManager forgetInstance(array|string|null $name = null)
 * @method static \Voyager\Concurrency\ConcurrencyManager extend(string $name, \Closure $callback)
 * @method static \Voyager\Concurrency\ConcurrencyManager setApplication(\Voyager\Contracts\Core\Program $app)
 *
 * @see \Voyager\Concurrency\ConcurrencyManager
 */
class Concurrency extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return ConcurrencyManager::class;
    }
}
