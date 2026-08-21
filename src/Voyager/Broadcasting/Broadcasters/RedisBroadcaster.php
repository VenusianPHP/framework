<?php

namespace Voyager\Broadcasting\Broadcasters;

use Voyager\Broadcasting\BroadcastException;
use Voyager\Contracts\Redis\Factory as Redis;
use Voyager\Redis\Connections\PhpRedisClusterConnection;
use Voyager\Redis\Connections\PredisClusterConnection;
use Voyager\Redis\Connections\PredisConnection;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Predis\Connection\Cluster\RedisCluster;
use Predis\Connection\ConnectionException;
use RedisException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RedisBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /**
     * The Redis instance.
     *
     * @var \Voyager\Contracts\Redis\Factory
     */
    protected $redis;

    /**
     * The Redis connection to use for broadcasting.
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * The Redis key prefix.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Create a new broadcaster instance.
     *
     * @param  \Voyager\Contracts\Redis\Factory  $redis
     * @param  string|null  $connection
     * @param  string  $prefix
     */
    public function __construct(Redis $redis, $connection = null, $prefix = '')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->connection = $connection;
    }

    // Laravel authenticates an incoming request for a channel here.
    // Receiving requests is out of scope for this port, so the auth surface
    // is cut and only the broadcast path remains.

    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     *
     * @throws \Voyager\Broadcasting\BroadcastException
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        if (empty($channels)) {
            return;
        }

        $connection = $this->redis->connection($this->connection);

        $payload = json_encode([
            'event' => $event,
            'data' => $payload,
            'socket' => Arr::pull($payload, 'socket'),
        ]);

        try {
            if ($connection instanceof PhpRedisClusterConnection) {
                foreach ($channels as $channel) {
                    $connection->publish($channel, $payload);
                }
            } elseif ($connection instanceof PredisClusterConnection &&
                $connection->client()->getConnection() instanceof RedisCluster) {
                $randomClusterNodeConnection = new PredisConnection(
                    $connection->client()->getClientBy('slot', mt_rand(0, 16383))
                );

                if ($events = $connection->getEventDispatcher()) {
                    $randomClusterNodeConnection->setEventDispatcher($events);
                }

                $randomClusterNodeConnection->eval(
                    $this->broadcastMultipleChannelsScript(),
                    0, $payload, ...$this->formatChannels($channels)
                );
            } else {
                $connection->eval(
                    $this->broadcastMultipleChannelsScript(),
                    0, $payload, ...$this->formatChannels($channels)
                );
            }
        } catch (ConnectionException|RedisException $e) {
            throw new BroadcastException(
                sprintf('Redis error: %s.', $e->getMessage())
            );
        }
    }

    /**
     * Get the Lua script for broadcasting to multiple channels.
     *
     * ARGV[1] - The payload
     * ARGV[2...] - The channels
     *
     * @return string
     */
    protected function broadcastMultipleChannelsScript()
    {
        return <<<'LUA'
for i = 2, #ARGV do
  redis.call('publish', ARGV[i], ARGV[1])
end
LUA;
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
            return $this->prefix.$channel;
        }, parent::formatChannels($channels));
    }
}
