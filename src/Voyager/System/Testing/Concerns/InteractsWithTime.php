<?php

namespace Voyager\System\Testing\Concerns;

use Voyager\System\Testing\Wormhole;
use Voyager\NutsAndBolts\DataObjects\Carbon;

trait InteractsWithTime
{
    /**
     * @template TReturn of mixed
     *
     * Freeze time.
     *
     * @param  (callable(\Voyager\NutsAndBolts\DataObjects\Carbon): TReturn)|null  $callback
     * @return ($callback is null ? \Voyager\NutsAndBolts\DataObjects\Carbon : TReturn)
     */
    public function freezeTime($callback = null)
    {
        $result = $this->travelTo($now = Carbon::now(), $callback);

        return is_null($callback) ? $now : $result;
    }

    /**
     * @template TReturn of mixed
     *
     * Freeze time at the beginning of the current second.
     *
     * @param  (callable(\Voyager\NutsAndBolts\DataObjects\Carbon): TReturn)|null  $callback
     * @return ($callback is null ? \Voyager\NutsAndBolts\DataObjects\Carbon : TReturn)
     */
    public function freezeSecond($callback = null)
    {
        $result = $this->travelTo($now = Carbon::now()->startOfSecond(), $callback);

        return is_null($callback) ? $now : $result;
    }

    /**
     * Begin travelling to another time.
     *
     * @param  int  $value
     * @return \Voyager\System\Testing\Wormhole
     */
    public function travel($value)
    {
        return new Wormhole($value);
    }

    /**
     * @template TReturn of mixed
     * @template TDate of \DateTimeInterface|\Closure|\Voyager\NutsAndBolts\DataObjects\Carbon|string|bool|null
     *
     * Travel to another time.
     *
     * @param  TDate  $date
     * @param  (callable(TDate): TReturn)|null  $callback
     * @return ($callback is null ? void : TReturn)
     */
    public function travelTo($date, $callback = null)
    {
        Carbon::setTestNow($date);

        if ($callback) {
            return tap($callback($date), function () {
                Carbon::setTestNow();
            });
        }
    }

    /**
     * Travel back to the current time.
     *
     * @return \DateTimeInterface
     */
    public function travelBack()
    {
        return Wormhole::back();
    }
}
