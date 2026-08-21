# Deferred `Redis` tests

Ported from `laravel/framework@v12.67.0:tests/Redis`, excluded in `phpunit.xml`.

All four use `Voyager\System\Testing\Concerns\InteractsWithRedis`, which landed
with Testing. What still blocks them is a live Redis server on
`REDIS_HOST`/`REDIS_PORT` — restoring them means wiring a service into CI.

* `RedisConnectionTest.php`
* `RedisConnectorTest.php`
* `DurationLimiterTest.php`
* `ConcurrentLimiterTest.php`

The three that run without a server — `RedisManagerExtensionTest`,
`RedisEventsTest` and `Connections/PhpRedisClusterConnectionTest` — are in
`tests/Redis`.
