<?php

namespace Voyager\System\Bus;

use Closure;
use Voyager\Contracts\Bus\Dispatcher;
use Voyager\NutsAndBolts\Fluent;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @param  mixed  ...$arguments
     * @return \Voyager\System\Bus\PendingDispatch
     */
    public static function dispatch(mixed ...$arguments): PendingDispatch
    {
        return static::newPendingDispatch(new static(...$arguments));
    }

    /**
     * Dispatch the job with the given arguments if the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     * @return \Voyager\System\Bus\PendingDispatch|\Voyager\NutsAndBolts\Fluent
     */
    public static function dispatchIf(bool|\Closure $boolean, mixed ...$arguments): PendingDispatch|Fluent
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);

            return value($boolean, $dispatchable)
                ? static::newPendingDispatch($dispatchable)
                : new Fluent;
        }

        return value($boolean)
            ? static::newPendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Dispatch the job with the given arguments unless the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     * @return \Voyager\System\Bus\PendingDispatch|\Voyager\NutsAndBolts\Fluent
     */
    public static function dispatchUnless(bool|\Closure $boolean, mixed ...$arguments): PendingDispatch|Fluent
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);

            return ! value($boolean, $dispatchable)
                ? static::newPendingDispatch($dispatchable)
                : new Fluent;
        }

        return ! value($boolean)
            ? static::newPendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchSync(mixed ...$arguments): mixed
    {
        return app(Dispatcher::class)->dispatchSync(new static(...$arguments));
    }

    /**
     * Dispatch a command to its appropriate handler after the current process.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchAfterResponse(mixed ...$arguments): mixed
    {
        return self::dispatch(...$arguments)->afterResponse();
    }

    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param  array  $chain
     * @return \Voyager\System\Bus\PendingChain
     */
    public static function withChain(array $chain): PendingChain
    {
        return new PendingChain(static::class, $chain);
    }

    /**
     * Create a new pending job dispatch instance.
     *
     * @param  mixed  $job
     * @return \Voyager\System\Bus\PendingDispatch
     */
    protected static function newPendingDispatch(mixed $job): PendingDispatch
    {
        return new PendingDispatch($job);
    }
}
