<?php

use Carbon\CarbonInterval;
use Carbon\CarbonInterface;
use Voyager\NutsAndBolts\DataObjects\Carbon;

use function Voyager\NutsAndBolts\Helpers\enum_value;

if (! function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param UnitEnum|DateTimeZone|string|null $tz
     * @return Carbon
     */
    function now(UnitEnum|DateTimeZone|string|null $tz = null): CarbonInterface
    {
        // Interim: this delegated to the Date magic alias, which resolves a 'date'
        // container binding and so cannot work until the System layer exists.
        // Restore `Date::now(...)` once MagicAliases lands.
        return Carbon::now(enum_value($tz));
    }
}

if (! function_exists('microseconds')) {
    /**
     * Get the current date / time plus the given number of microseconds.
     */
    function microseconds(int|float $microseconds): CarbonInterval
    {
        return CarbonInterval::microseconds($microseconds);
    }
}

if (! function_exists('milliseconds')) {
    /**
     * Get the current date / time plus the given number of milliseconds.
     */
    function milliseconds(int|float $milliseconds): CarbonInterval
    {
        return CarbonInterval::milliseconds($milliseconds);
    }
}

if (! function_exists('seconds')) {
    /**
     * Get the current date / time plus the given number of seconds.
     */
    function seconds(int|float $seconds): CarbonInterval
    {
        return CarbonInterval::seconds($seconds);
    }
}

if (! function_exists('minutes')) {
    /**
     * Get the current date / time plus the given number of minutes.
     */
    function minutes(int|float $minutes): CarbonInterval
    {
        return CarbonInterval::minutes($minutes);
    }
}

if (! function_exists('hours')) {
    /**
     * Get the current date / time plus the given number of hours.
     */
    function hours(int|float $hours): CarbonInterval
    {
        return CarbonInterval::hours($hours);
    }
}

if (! function_exists('days')) {
    /**
     * Get the current date / time plus the given number of days.
     */
    function days(int|float $days): CarbonInterval
    {
        return CarbonInterval::days($days);
    }
}

if (! function_exists('weeks')) {
    /**
     * Get the current date / time plus the given number of weeks.
     */
    function weeks(int $weeks): CarbonInterval
    {
        return CarbonInterval::weeks($weeks);
    }
}

if (! function_exists('months')) {
    /**
     * Get the current date / time plus the given number of months.
     */
    function months(int $months): CarbonInterval
    {
        return CarbonInterval::months($months);
    }
}

if (! function_exists('years')) {
    /**
     * Get the current date / time plus the given number of years.
     */
    function years(int $years): CarbonInterval
    {
        return CarbonInterval::years($years);
    }
}