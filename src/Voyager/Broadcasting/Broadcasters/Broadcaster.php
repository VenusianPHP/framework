<?php

namespace Voyager\Broadcasting\Broadcasters;

use Closure;
use Exception;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Voyager\Contracts\Broadcasting\HasBroadcastChannel;
use Voyager\NutsAndBolts\Collection;

abstract class Broadcaster implements BroadcasterContract
{
    /**
     * The callback to resolve the authenticated user information.
     *
     * @var \Closure|null
     */
    protected $authenticatedUserCallback = null;

    /**
     * The registered channel authenticators.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * The registered channel options.
     *
     * @var array
     */
    protected $channelOptions = [];

    /**
     * The binding registrar instance.
     *
     * @var \Voyager\Contracts\Routing\BindingRegistrar
     */
    protected $bindingRegistrar;

    // Laravel authenticates an incoming request for a channel here.
    // Receiving requests is out of scope for this port, so the auth surface
    // is cut and only the broadcast path remains.

    /**
     * Register a channel authenticator.
     *
     * @param  \Voyager\Contracts\Broadcasting\HasBroadcastChannel|string  $channel
     * @param  callable|string  $callback
     * @param  array  $options
     * @return $this
     */
    public function channel($channel, $callback, $options = [])
    {
        if ($channel instanceof HasBroadcastChannel) {
            $channel = $channel->broadcastChannelRoute();
        } elseif (is_string($channel) && class_exists($channel) && is_a($channel, HasBroadcastChannel::class, true)) {
            $channel = (new $channel)->broadcastChannelRoute();
        }

        $this->channels[$channel] = $callback;

        $this->channelOptions[$channel] = $options;

        return $this;
    }

    /**
     * Format the channel array into an array of strings.
     *
     * @param  array  $channels
     * @return array
     */
    protected function formatChannels(array $channels)
    {
        return array_map(function ($channel) {
            return (string) $channel;
        }, $channels);
    }

    /**
     * Normalize the given callback into a callable.
     *
     * @param  mixed  $callback
     * @return callable
     */
    protected function normalizeChannelHandlerToCallable($callback)
    {
        return is_callable($callback) ? $callback : function (...$args) use ($callback) {
            return Vessel::getInstance()
                ->make($callback)
                ->join(...$args);
        };
    }

    /**
     * Retrieve options for a certain channel.
     *
     * @param  string  $channel
     * @return array
     */
    protected function retrieveChannelOptions($channel)
    {
        foreach ($this->channelOptions as $pattern => $options) {
            if (! $this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            return $options;
        }

        return [];
    }

    /**
     * Check if the channel name from the request matches a pattern from registered channels.
     *
     * @param  string  $channel
     * @param  string  $pattern
     * @return bool
     */
    protected function channelNameMatchesPattern($channel, $pattern)
    {
        $pattern = str_replace('.', '\.', $pattern);

        return preg_match('/^'.preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern).'$/', $channel);
    }

    /**
     * Get all of the registered channels.
     *
     * @return \Voyager\NutsAndBolts\Collection
     */
    public function getChannels()
    {
        return new Collection($this->channels);
    }
}
