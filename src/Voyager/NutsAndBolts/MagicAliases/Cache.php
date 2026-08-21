<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;
use Mockery;

/**
 * @method static \Voyager\Contracts\Cache\Repository store(\UnitEnum|string|null $name = null)
 * @method static \Voyager\Contracts\Cache\Repository driver(\UnitEnum|string|null $driver = null)
 * @method static \Voyager\Contracts\Cache\Repository memo(\UnitEnum|string|null $driver = null)
 * @method static \Voyager\Contracts\Cache\Repository resolve(string $name)
 * @method static \Voyager\Cache\CacheRepository build(array $config)
 * @method static \Voyager\Cache\CacheRepository repository(\Voyager\Contracts\Cache\Store $store, array $config = [])
 * @method static void refreshEventDispatcher()
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(\UnitEnum|string $name)
 * @method static \Voyager\Cache\CacheManager forgetDriver(array|\UnitEnum|string|null $name = null)
 * @method static void purge(\UnitEnum|string|null $name = null)
 * @method static \Voyager\Cache\CacheManager extend(string $driver, \Closure $callback)
 * @method static \Voyager\Cache\CacheManager setApplication(\Voyager\Contracts\Core\Machine $app)
 * @method static void handleUnserializableClassUsing(callable|null $callback)
 * @method static bool has(\UnitEnum|array|string $key)
 * @method static bool missing(\UnitEnum|string $key)
 * @method static mixed get(\UnitEnum|array|string $key, mixed $default = null)
 * @method static array many(array $keys)
 * @method static iterable getMultiple(iterable $keys, mixed $default = null)
 * @method static mixed pull(\UnitEnum|array|string $key, mixed $default = null)
 * @method static bool put(\UnitEnum|array|string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool set(\UnitEnum|array|string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool putMany(array $values, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool add(\UnitEnum|array|string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static int|bool increment(\UnitEnum|string $key, mixed $value = 1)
 * @method static int|bool decrement(\UnitEnum|string $key, mixed $value = 1)
 * @method static bool forever(\UnitEnum|string $key, mixed $value)
 * @method static mixed remember(\UnitEnum|string $key, \Closure|\DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback)
 * @method static mixed rememberForever(\UnitEnum|string $key, \Closure $callback)
 * @method static bool forget(\UnitEnum|array|string $key)
 * @method static bool delete(\UnitEnum|array|string $key)
 * @method static bool clear()
 * @method static bool flush()
 * @method static \Voyager\Cache\TaggedCache tags(mixed $names)
 * @method static \Voyager\Contracts\Cache\Store getStore()
 * @method static \Voyager\Contracts\Events\Dispatcher|null getEventDispatcher()
 * @method static void setEventDispatcher(\Voyager\Contracts\Events\Dispatcher $events)
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 *
 * @see \Voyager\Cache\CacheManager
 * @see \Voyager\Cache\CacheRepository
 */
class Cache extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'cache';
    }

    /**
     * Convert the magic alias into a Mockery spy.
     *
     * @return \Mockery\MockInterface
     */
    public static function spy(): Mockery\MockInterface
    {
        if (! static::isMock()) {
            $class = static::getMockableClass();
            $instance = static::getMagicAliasRoot();

            if ($class && $instance) {
                return tap(Mockery::spy($instance)->makePartial(), function ($spy) {
                    static::swap($spy);
                });
            }

            return tap($class ? Mockery::spy($class) : Mockery::spy(), function ($spy) {
                static::swap($spy);
            });
        }

        return static::getMagicAliasRoot();
    }
}
