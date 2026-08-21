<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\Http\Client\Factory;
use Voyager\MagicAliases\MagicAlias;

/**
 * @method static \Voyager\Http\Client\PendingRequest accept(string $contentType)
 * @method static \Voyager\Http\Client\PendingRequest acceptJson()
 * @method static \Voyager\Http\Client\PendingRequest asForm()
 * @method static \Voyager\Http\Client\PendingRequest asJson()
 * @method static \Voyager\Http\Client\Response get(string $url, array|string|null $query = null)
 * @method static \Voyager\Http\Client\Response post(string $url, array $data = [])
 * @method static \Voyager\Http\Client\Response put(string $url, array $data = [])
 * @method static \Voyager\Http\Client\Response patch(string $url, array $data = [])
 * @method static \Voyager\Http\Client\Response delete(string $url, array $data = [])
 * @method static \Voyager\Http\Client\Response send(string $method, string $url, array $options = [])
 * @method static \Voyager\Http\Client\ResponseSequence sequence(array $responses = [])
 * @method static \Voyager\Http\Client\Factory preventStrayRequests(bool $prevent = true)
 * @method static void assertSent(callable $callback)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 *
 * @see \Voyager\Http\Client\Factory
 */
class Http extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return Factory::class;
    }

    /**
     * Replace outbound requests with deterministic fake responses.
     *
     * @param  callable|array|null  $callback
     */
    public static function fake(callable|array|null $callback = null): Factory
    {
        static::getMagicAliasRoot()->fake($callback);

        return static::getMagicAliasRoot();
    }
}
