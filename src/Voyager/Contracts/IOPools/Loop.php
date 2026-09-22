<?php

namespace Voyager\Contracts\IOPools;

use Closure;

interface Loop
{
    /**
     * Runs the loop
     * @return int
     */
    public function run(): int;

    /**
     * Checks the state of the loop
     * @return bool
     */
    public function running(): bool;

    /**
     * Runs the loop during a blocking action.
     * @param Closure $assertion
     * @return void
     */
    public function until(Closure $assertion): void;

    /**
     * Stops the loop
     * @param int $status
     * @return void
     */
    public function stop(int $status = 0): void;

    /**
     * Registers a one-shot timer that fires after $delay_s
     * @param float $delay_s
     * @param callable $fire
     * @return LoopTimer
     */
    public function at(float $delay_s, callable $fire): LoopTimer;

    /**
     * Registers a Tickable resource
     * @param string $name
     * @param Sourceable $resource
     * @return Sourceable
     */
    public function resource(string $name, Sourceable $resource): Sourceable;

    /**
     * Registers a re-curring timer that fires every $interval_s
     * @param float $interval_s
     * @param callable $fire
     * @param string $name
     * @return LoopTimer
     */
    public function every(float $interval_s, callable $fire, string $name): LoopTimer;

    /**
     * Removes a resource
     * @param string $name
     * @return void
     */
    public function forget(string $name): void;

    /**
     * Makes a new pending promise on this loop
     * @return Promise
     */
    public function promise(): Promise;

    /**
     * Waits on a promise, any thenable, or hands a plain value straight back
     * @param mixed $value
     * @return mixed
     */
    public function await(mixed $value): mixed;

    /**
     * Wrap a foreign thenable as a loop promise. Does not wait.
     * @param object $thenable
     * @return Promise
     */
    public function adopt(object $thenable): Promise;

    /**
     * Runs $body in a fiber. Any wait() or until() under it suspends instead of borrowing
     * the loop, so the turn keeps going and mail keeps flowing. Starts now, runs to its
     * first wait before returning.
     * @param callable $body
     * @return Task
     */
    public function async(callable $body): Task;

    /**
     * Runs $work on the loop's next turn. The promise settles with its return, or rejects with what it threw.
     * @param Closure $work
     * @return Promise
     */
    public function defer(Closure $work): Promise;

    /**
     * Registers a hook that fires once run() ends, whether by stop() or by running out of work.
     * Not fired by until().
     */
    public function onStop(callable $hook): void;
}
