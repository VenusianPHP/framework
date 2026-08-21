<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

use Voyager\Contracts\Broadcasting\Factory as BroadcastingFactoryContract;

/**
 * @method static void routes(array|null $attributes = null)
 * @method static void userRoutes(array|null $attributes = null)
 * @method static void channelRoutes(array|null $attributes = null)
 * @method static string|null socket(\Voyager\Http\Request|null $request = null)
 * @method static \Voyager\Broadcasting\AnonymousEvent on(\Voyager\Broadcasting\Channel|array|string $channels)
 * @method static \Voyager\Broadcasting\AnonymousEvent private(string $channel)
 * @method static \Voyager\Broadcasting\AnonymousEvent presence(string $channel)
 * @method static \Voyager\Broadcasting\PendingBroadcast event(mixed $event = null)
 * @method static void queue(mixed $event)
 * @method static mixed connection(string|null $driver = null)
 * @method static mixed driver(string|null $name = null)
 * @method static \Pusher\Pusher pusher(array $config)
 * @method static \Ably\AblyRest ably(array $config)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static void purge(string|null $name = null)
 * @method static \Voyager\Broadcasting\BroadcastManager extend(string $driver, \Closure $callback)
 * @method static \Voyager\Contracts\System\Application getApplication()
 * @method static \Voyager\Broadcasting\BroadcastManager setApplication(\Voyager\Contracts\System\Application $app)
 * @method static \Voyager\Broadcasting\BroadcastManager forgetDrivers()
 * @method static mixed auth(\Voyager\Http\Request $request)
 * @method static mixed validAuthenticationResponse(\Voyager\Http\Request $request, mixed $result)
 * @method static void broadcast(array $channels, string $event, array $payload = [])
 * @method static array|null resolveAuthenticatedUser(\Voyager\Http\Request $request)
 * @method static void resolveAuthenticatedUserUsing(\Closure $callback)
 * @method static \Voyager\Broadcasting\Broadcasters\Broadcaster channel(\Voyager\Contracts\Broadcasting\HasBroadcastChannel|string $channel, callable|string $callback, array $options = [])
 * @method static \Voyager\NutsAndBolts\Collection getChannels()
 *
 * @see \Voyager\Broadcasting\BroadcastManager
 * @see \Voyager\Broadcasting\Broadcasters\Broadcaster
 */
class Broadcast extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return BroadcastingFactoryContract::class;
    }
}
