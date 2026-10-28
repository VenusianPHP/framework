<?php

namespace Voyager\Contracts\IOPools;

use Closure;
use Voyager\Contracts\IOPools\LoopResources\Deadlined;
use Voyager\Contracts\IOPools\LoopResources\Timer;

interface Loop
{
    /**
     * Registers a one-shot timer that fires after $delay_s
     * @param float $delay_s
     * @param callable $fire
     * @return Timer
     */
    public function at(float $delay_s, callable $fire): Timer;

    /**
     * Registers a re-curring timer that fires every $interval_s
     * @param float $interval_s
     * @param callable $fire
     * @param string $name
     * @return Timer
     */
    public function every(float $interval_s, callable $fire, string $name): Timer;

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
     * Runs $body in a fiber. Starts now, runs to its first wait before returning.
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
     * Queues mail for the loop's next delivery, as if a resource had pumped it.
     * A borrowed turn in until() keeps it in the bag; the next turn of run() delivers it.
     * @param object $mail
     * @return void
     */
    public function post(object $mail): void;

    /**
     * Registers a loop resource
     * @param string $name
     * @param LoopResource $resource
     * @return LoopResource
     */
    public function resource(string $name, LoopResource $resource): LoopResource;

    /**
     * Removes a resource. A sleeper that held the sleep passes it to the next in line.
     * @param string $name
     * @return void
     */
    public function forget(string $name): void;

    /**
     * Moves a registered sleeper to the head of the line.
     * @param string $name
     * @return void
     */
    public function crown(string $name): void;

    /**
     * Whether a resource may declare this kind of wake on this loop.
     * @param WakeReason $kind
     * @return bool
     */
    public function supports(WakeReason $kind): bool;

    /**
     * The waiter's descriptor: readable whenever this loop's wait would return something.
     * A native event loop that sleeps for this one (a GUI toolkit's) watches it so every
     * wake of this loop also ends that sleep. Null when the waiter backend has none.
     * @return int|null
     */
    public function descriptor(): ?int;

    /**
     * Registers a hook that fires once run() ends, whether by stop() or by running out of work.
     * Not fired by until().
     */
    public function onStop(callable $hook): void;

    /**
     * Ask $handler first when a stop signal (SIGINT, SIGTERM) arrives. A handler that returns
     * true takes the signal: the loop keeps running, and the handler stops it once it is done
     * (a server answering what is in flight first). With no handler taking it, the loop stops as
     * it always has. A second signal is never offered: it ends the process as if nobody listened.
     *
     * @param  callable(int): bool  $handler  given the signal number
     */
    public function onStopSignal(callable $handler): void;

    /**
     * Never listen for stop signals: leave SIGINT and SIGTERM, and whatever handles them, to the
     * process. For a loop that doesn't own its process's signals: one in a thread, or one nested in
     * a runner that handles them itself.
     */
    public function ignoreStopSignals(): void;

    /**
     * Runs the loop
     * @return int
     */
    public function run(): int;

    /**
     * Stops the loop
     * @param int $status
     * @return void
     */
    public function stop(int $status = 0): void;

    /**
     * Runs the loop during a blocking action.
     * @param Closure $assertion
     * @return void
     */
    public function until(Closure $assertion): void;
}