<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;
use Voyager\Queue\Worker;
use Voyager\Testing\Fakes\QueueFake;

/**
 * @method static void before(mixed $callback)
 * @method static void after(mixed $callback)
 * @method static void exceptionOccurred(mixed $callback)
 * @method static void looping(mixed $callback)
 * @method static void failing(mixed $callback)
 * @method static void starting(mixed $callback)
 * @method static void stopping(mixed $callback)
 * @method static void route(array|string $class, \UnitEnum|string|null $queue = null, \UnitEnum|string|null $connection = null)
 * @method static bool connected(\UnitEnum|string|null $name = null)
 * @method static \Voyager\Contracts\Queue\Queue connection(\UnitEnum|string|null $name = null)
 * @method static void extend(string $driver, \Closure $resolver)
 * @method static void addConnector(string $driver, \Closure $resolver)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(\UnitEnum|string $name)
 * @method static string getName(string|null $connection = null)
 * @method static mixed getApplication()
 * @method static \Voyager\Queue\QueueManager setApplication(mixed $app)
 * @method static string|null resolveConnectionFromQueueRoute(object $queueable)
 * @method static string|null resolveQueueFromQueueRoute(object $queueable)
 * @method static int size(string|null $queue = null)
 * @method static mixed push(string|object $job, mixed $data = '', string|null $queue = null)
 * @method static mixed pushOn(string $queue, string|object $job, mixed $data = '')
 * @method static mixed pushRaw(string $payload, string|null $queue = null, array $options = [])
 * @method static mixed later(\DateTimeInterface|\DateInterval|int $delay, string|object $job, mixed $data = '', string|null $queue = null)
 * @method static mixed laterOn(string $queue, \DateTimeInterface|\DateInterval|int $delay, string|object $job, mixed $data = '')
 * @method static mixed bulk(array $jobs, mixed $data = '', string|null $queue = null)
 * @method static \Voyager\Contracts\Queue\Job|null pop(string|null $queue = null)
 * @method static string getConnectionName()
 * @method static \Voyager\Contracts\Queue\Queue setConnectionName(string $name)
 *
 * @see \Voyager\Queue\QueueManager
 * @see \Voyager\Queue\SyncQueue
 */
class Queue extends MagicAlias
{
    /**
     * Register a callback to be executed to pick jobs.
     *
     * @param  string  $workerName
     * @param  callable  $callback
     * @return void
     */
    public static function popUsing($workerName, $callback)
    {
        Worker::popUsing($workerName, $callback);
    }

    /**
     * Replace the bound instance with a fake.
     *
     * @param  array|string  $jobsToFake
     * @return \Voyager\Testing\Fakes\QueueFake
     */
    public static function fake($jobsToFake = [])
    {
        $actualQueueManager = static::isFake()
            ? tap(static::getMagicAliasRoot(), fn ($fake) => $fake->releaseUniqueJobLocks())->queue
            : static::getMagicAliasRoot();

        return tap(new QueueFake(static::getMagicAliasApplication(), $jobsToFake, $actualQueueManager), function ($fake) {
            static::swap($fake);
        });
    }

    /**
     * Replace the bound instance with a fake that fakes every job except the given jobs.
     *
     * @param  string[]|string  $jobsToAllow
     * @return \Voyager\Testing\Fakes\QueueFake
     */
    public static function fakeExcept($jobsToAllow)
    {
        return static::fake()->except($jobsToAllow);
    }

    /**
     * Replace the bound instance with a fake for the given callable's execution.
     *
     * @param  callable  $callable
     * @param  array  $jobsToFake
     * @return mixed
     */
    public static function fakeFor(callable $callable, array $jobsToFake = [])
    {
        $originalQueueManager = static::getMagicAliasRoot();

        static::fake($jobsToFake);

        try {
            return $callable();
        } finally {
            static::swap($originalQueueManager);
        }
    }

    /**
     * Replace the bound instance with a fake for the given callable's execution.
     *
     * @param  callable  $callable
     * @param  array  $jobsToAllow
     * @return mixed
     */
    public static function fakeExceptFor(callable $callable, array $jobsToAllow = [])
    {
        $originalQueueManager = static::getMagicAliasRoot();

        static::fakeExcept($jobsToAllow);

        try {
            return $callable();
        } finally {
            static::swap($originalQueueManager);
        }
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'queue';
    }
}
