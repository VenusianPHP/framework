<?php

namespace Voyager\System\Events;

trait Dispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatch(mixed ...$arguments): mixed
    {
        return event(new static(...$arguments));
    }

    /**
     * Dispatch the event with the given arguments if the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchIf(bool $boolean, mixed ...$arguments): mixed
    {
        if ($boolean) {
            return event(new static(...$arguments));
        }
    }

    /**
     * Dispatch the event with the given arguments unless the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchUnless(bool $boolean, mixed ...$arguments): mixed
    {
        if (! $boolean) {
            return event(new static(...$arguments));
        }
    }

    /**
     * Broadcast the event with the given arguments.
     *
     * @param  mixed  ...$arguments
     * @return \Voyager\Broadcasting\PendingBroadcast
     */
    public static function broadcast(mixed ...$arguments): \Voyager\Broadcasting\PendingBroadcast
    {
        return broadcast(new static(...$arguments));
    }
}
