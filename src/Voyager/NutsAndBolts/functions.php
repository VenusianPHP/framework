<?php

namespace Voyager\NutsAndBolts;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Voyager\NutsAndBolts\Defer\DeferredCallback;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\NutsAndBolts\MagicAliases\Date;
use Symfony\Component\Process\PhpExecutableFinder;

if (! function_exists('Voyager\NutsAndBolts\defer')) {
    /**
     * Defer execution of the given callback.
     *
     * @param  callable|null  $callback
     * @param  string|null  $name
     * @param  bool  $always
     * @return ($callback is null ? \Voyager\NutsAndBolts\Defer\DeferredCallbackCollection : \Voyager\NutsAndBolts\Defer\DeferredCallback)
     */
    function defer(?callable $callback = null, ?string $name = null, bool $always = false): DeferredCallback|DeferredCallbackCollection
    {
        if ($callback === null) {
            return app(DeferredCallbackCollection::class);
        }

        return tap(
            new DeferredCallback($callback, $name, $always),
            fn ($deferred) => app(DeferredCallbackCollection::class)[] = $deferred
        );
    }
}

if (! function_exists('Voyager\NutsAndBolts\php_binary')) {
    /**
     * Determine the PHP Binary.
     */
    function php_binary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}

if (! function_exists('Voyager\NutsAndBolts\computer_binary')) {
    /**
     * Determine the proper Computer executable.
     */
    function computer_binary(): string
    {
        return defined('COMPUTER_BINARY') ? COMPUTER_BINARY : 'computer';
    }
}

// Time functions...

if (! function_exists('Voyager\NutsAndBolts\now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param  \DateTimeZone|\UnitEnum|string|null  $tz
     * @return \Voyager\NutsAndBolts\DataObjects\Carbon
     */
    function now($tz = null): CarbonInterface
    {
        return Date::now(enum_value($tz));
    }
}

if (! function_exists('Voyager\NutsAndBolts\microseconds')) {
    /**
     * Get the current date / time plus the given number of microseconds.
     */
    function microseconds(int|float $microseconds): CarbonInterval
    {
        return CarbonInterval::microseconds($microseconds);
    }
}

if (! function_exists('Voyager\NutsAndBolts\milliseconds')) {
    /**
     * Get the current date / time plus the given number of milliseconds.
     */
    function milliseconds(int|float $milliseconds): CarbonInterval
    {
        return CarbonInterval::milliseconds($milliseconds);
    }
}

if (! function_exists('Voyager\NutsAndBolts\seconds')) {
    /**
     * Get the current date / time plus the given number of seconds.
     */
    function seconds(int|float $seconds): CarbonInterval
    {
        return CarbonInterval::seconds($seconds);
    }
}

if (! function_exists('Voyager\NutsAndBolts\minutes')) {
    /**
     * Get the current date / time plus the given number of minutes.
     */
    function minutes(int|float $minutes): CarbonInterval
    {
        return CarbonInterval::minutes($minutes);
    }
}

if (! function_exists('Voyager\NutsAndBolts\hours')) {
    /**
     * Get the current date / time plus the given number of hours.
     */
    function hours(int|float $hours): CarbonInterval
    {
        return CarbonInterval::hours($hours);
    }
}

if (! function_exists('Voyager\NutsAndBolts\days')) {
    /**
     * Get the current date / time plus the given number of days.
     */
    function days(int|float $days): CarbonInterval
    {
        return CarbonInterval::days($days);
    }
}

if (! function_exists('Voyager\NutsAndBolts\weeks')) {
    /**
     * Get the current date / time plus the given number of weeks.
     */
    function weeks(int $weeks): CarbonInterval
    {
        return CarbonInterval::weeks($weeks);
    }
}

if (! function_exists('Voyager\NutsAndBolts\months')) {
    /**
     * Get the current date / time plus the given number of months.
     */
    function months(int $months): CarbonInterval
    {
        return CarbonInterval::months($months);
    }
}

if (! function_exists('Voyager\NutsAndBolts\years')) {
    /**
     * Get the current date / time plus the given number of years.
     */
    function years(int $years): CarbonInterval
    {
        return CarbonInterval::years($years);
    }
}
