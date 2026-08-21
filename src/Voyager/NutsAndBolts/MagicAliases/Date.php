<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\DateFactory;
use RuntimeException;

/**
 * @see https://carbon.nesbot.com/docs/
 * @see https://github.com/briannesbitt/Carbon/blob/master/src/Carbon/Factory.php
 *
 * @method static \Voyager\NutsAndBolts\DataObjects\Carbon now(\DateTimeZone|string|int|null $timezone = null)
 */
class Date extends MagicAlias
{
    const string DEFAULT_MAGIC_ALIAS = DateFactory::class;

    /**
     * Get the registered name of the component.
     *
     * @return string
     * @throws RuntimeException
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'date';
    }

    /**
     * Resolve the magic alias root instance from the container.
     *
     * Falls back to a plain date factory when nothing is bound, so `Date::now()`
     * works before — or without — a container binding, as upstream does.
     *
     * @param  string  $name
     * @return mixed
     */
    protected static function resolveMagicAliasInstance(string $name): mixed
    {
        if (! isset(static::$resolvedInstance[$name]) && ! isset(static::$vessel, static::$vessel[$name])) {
            $class = static::DEFAULT_MAGIC_ALIAS;

            static::swap(new $class);
        }

        return parent::resolveMagicAliasInstance($name);
    }
}
