<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

use Voyager\Notifications\AnonymousNotifiable;
use Voyager\Notifications\ChannelManager;
use Voyager\Testing\Fakes\NotificationFake;

/**
 * @method static void send(\Voyager\NutsAndBolts\Collection|mixed $notifiables, mixed $notification)
 * @method static void sendNow(\Voyager\NutsAndBolts\Collection|mixed $notifiables, mixed $notification, array|null $channels = null)
 * @method static mixed channel(string|null $name = null)
 * @method static string getDefaultDriver()
 * @method static string deliversVia()
 * @method static void deliverVia(string $channel)
 * @method static \Voyager\Notifications\ChannelManager locale(string $locale)
 * @method static mixed driver(string|null $driver = null)
 * @method static \Voyager\Notifications\ChannelManager extend(string $driver, \Closure $callback)
 * @method static array getDrivers()
 * @method static \Voyager\Contracts\Vessel\Vessel getContainer()
 * @method static \Voyager\Notifications\ChannelManager setContainer(\Voyager\Contracts\Vessel\Vessel $container)
 * @method static \Voyager\Notifications\ChannelManager forgetDrivers()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static void assertSentOnDemand(string|\Closure $notification, callable|null $callback = null)
 * @method static void assertSentTo(mixed $notifiable, string|\Closure $notification, callable|null $callback = null)
 * @method static void assertSentOnDemandTimes(string $notification, int $times = 1)
 * @method static void assertSentToTimes(mixed $notifiable, string $notification, int $times = 1)
 * @method static void assertNotSentTo(mixed $notifiable, string|\Closure $notification, callable|null $callback = null)
 * @method static void assertNothingSent()
 * @method static void assertNothingSentTo(mixed $notifiable)
 * @method static void assertSentTimes(string $notification, int $expectedCount)
 * @method static void assertCount(int $expectedCount)
 * @method static \Voyager\NutsAndBolts\Collection sent(mixed $notifiable, string $notification, callable|null $callback = null)
 * @method static bool hasSent(mixed $notifiable, string $notification)
 * @method static \Voyager\Testing\Fakes\NotificationFake serializeAndRestore(bool $serializeAndRestore = true)
 * @method static array sentNotifications()
 *
 * @see \Voyager\Notifications\ChannelManager
 * @see \Voyager\Testing\Fakes\NotificationFake
 */
class Notification extends MagicAlias
{
    /**
     * Replace the bound instance with a fake.
     *
     * @return \Voyager\Testing\Fakes\NotificationFake
     */
    public static function fake()
    {
        return tap(new NotificationFake, function ($fake) {
            static::swap($fake);
        });
    }

    /**
     * Begin sending a notification to an anonymous notifiable on the given channels.
     *
     * @param  array  $channels
     * @return \Voyager\Notifications\AnonymousNotifiable
     */
    public static function routes(array $channels)
    {
        $notifiable = new AnonymousNotifiable;

        foreach ($channels as $channel => $route) {
            $notifiable->route($channel, $route);
        }

        return $notifiable;
    }

    /**
     * Begin sending a notification to an anonymous notifiable.
     *
     * @param  string  $channel
     * @param  mixed  $route
     * @return \Voyager\Notifications\AnonymousNotifiable
     */
    public static function route($channel, $route)
    {
        return (new AnonymousNotifiable)->route($channel, $route);
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return ChannelManager::class;
    }
}
