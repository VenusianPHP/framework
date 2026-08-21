<?php

namespace Voyager\NutsAndBolts\MagicAliases;

use Closure;
use Voyager\MagicAliases\MagicAlias;
use Voyager\Process\Factory;

/**
 * @method static \Voyager\Process\PendingProcess command(array|string $command)
 * @method static \Voyager\Process\PendingProcess path(string $path)
 * @method static \Voyager\Process\PendingProcess timeout(\Carbon\CarbonInterval|int $timeout)
 * @method static \Voyager\Process\PendingProcess idleTimeout(\Carbon\CarbonInterval|int $timeout)
 * @method static \Voyager\Process\PendingProcess forever()
 * @method static \Voyager\Process\PendingProcess env(array $environment)
 * @method static \Voyager\Process\PendingProcess input(\Traversable|resource|string|int|float|bool|null $input)
 * @method static \Voyager\Process\PendingProcess quietly()
 * @method static \Voyager\Process\PendingProcess tty(bool $tty = true)
 * @method static \Voyager\Process\PendingProcess options(array $options)
 * @method static \Voyager\Contracts\Process\ProcessResult run(array|string|null $command = null, callable|null $output = null)
 * @method static \Voyager\Process\InvokedProcess start(array|string|null $command = null, callable|null $output = null)
 * @method static bool supportsTty()
 * @method static \Voyager\Process\FakeProcessResult result(array|string $output = '', array|string $errorOutput = '', int $exitCode = 0)
 * @method static \Voyager\Process\FakeProcessDescription describe()
 * @method static \Voyager\Process\FakeProcessSequence sequence(array $processes = [])
 * @method static bool isRecording()
 * @method static \Voyager\Process\Factory preventStrayProcesses(bool $prevent = true)
 * @method static bool preventingStrayProcesses()
 * @method static \Voyager\Process\Factory assertRan(\Closure|string $callback)
 * @method static \Voyager\Process\Factory assertRanTimes(\Closure|string $callback, int $times = 1)
 * @method static \Voyager\Process\Factory assertNotRan(\Closure|string $callback)
 * @method static \Voyager\Process\Factory assertDidntRun(\Closure|string $callback)
 * @method static \Voyager\Process\Factory assertNothingRan()
 * @method static \Voyager\Process\Pool pool(callable $callback)
 * @method static \Voyager\Contracts\Process\ProcessResult pipe(callable|array $callback, callable|null $output = null)
 * @method static \Voyager\Process\ProcessPoolResults concurrently(callable $callback, callable|null $output = null)
 * @method static \Voyager\Process\PendingProcess newPendingProcess()
 *
 * @see \Voyager\Process\Factory
 */
class Process extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return Factory::class;
    }

    /**
     * Indicate that the process factory should fake processes.
     *
     * @param  \Closure|array|null  $callback
     * @return \Voyager\Process\Factory
     */
    public static function fake(Closure|array|null $callback = null): Factory
    {
        return tap(static::getMagicAliasRoot(), function ($fake) use ($callback) {
            static::swap($fake->fake($callback));
        });
    }
}
