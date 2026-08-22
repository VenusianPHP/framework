<?php

namespace Voyager\Workflows\Runtimes;

use Closure;
use Fiber;
use SplObjectStorage;
use Throwable;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Contracts\Workflows\WorkflowRuntimeException;
use Voyager\Workflows\Runtimes\Concerns\SettlesGroups;

/**
 * Overlaps work using native PHP fibers, with no external packages.
 *
 * Each unit of async work becomes a fiber. A fiber that awaits an unsettled
 * value suspends, and this runtime resumes it once the value arrives. Overlap
 * therefore happens at await points and at delays; a blocking call inside a
 * fiber still stalls everything, because nothing can preempt it.
 */
class FiberRuntime implements AsyncRuntime
{
    use SettlesGroups;

    /**
     * Fibers waiting to be started or resumed.
     *
     * @var array<int, Fiber>
     */
    private array $ready = [];

    /**
     * Fibers suspended until the awaitable they wait on settles.
     *
     * @var array<int, array{fiber: Fiber, awaitable: PendingAwaitable}>
     */
    private array $blocked = [];

    /**
     * @var array<int, array{at: float, awaitable: PendingAwaitable}>
     */
    private array $timers = [];

    /**
     * Fibers this runtime started and still owns.
     *
     * @var SplObjectStorage<Fiber, mixed>
     */
    private SplObjectStorage $owned;

    public function __construct()
    {
        $this->owned = new SplObjectStorage;
    }

    public function async(Closure $work): Awaitable
    {
        $awaitable = new PendingAwaitable;

        $fiber = new Fiber(function () use ($work, $awaitable) {
            try {
                $awaitable->fulfill($this->await($work()));
            } catch (Throwable $e) {
                $awaitable->reject($e);
            }
        });

        $this->owned->attach($fiber);

        // Start eagerly so work is underway before anyone awaits it. The fiber
        // runs until its first suspension point and then hands control back.
        $fiber->start();

        $this->release($fiber);

        return $awaitable;
    }

    public function resolve(mixed $value): Awaitable
    {
        if ($value instanceof Awaitable) {
            return $value;
        }

        $awaitable = new PendingAwaitable;
        $awaitable->fulfill($value);

        return $awaitable;
    }

    public function await(mixed $value): mixed
    {
        if (!$value instanceof Awaitable) {
            return $value;
        }

        if (!$value instanceof PendingAwaitable) {
            throw new WorkflowRuntimeException(
                'The fiber runtime cannot await ['.get_class($value).'], which belongs to another runtime.'
            );
        }

        $current = Fiber::getCurrent();

        if (!is_null($current) && $this->owned->contains($current)) {
            while (!$value->isSettled()) {
                $this->blocked[] = ['fiber' => $current, 'awaitable' => $value];

                Fiber::suspend();
            }

            return $value->unwrap();
        }

        $this->loop($value);

        return $value->unwrap();
    }

    public function delay(float $seconds): Awaitable
    {
        $awaitable = new PendingAwaitable;

        if ($seconds <= 0) {
            $awaitable->fulfill(null);

            return $awaitable;
        }

        $this->timers[] = ['at' => microtime(true) + $seconds, 'awaitable' => $awaitable];

        return $awaitable;
    }

    /**
     * Drive scheduled work until the given awaitable settles.
     *
     * @throws WorkflowRuntimeException If nothing can make progress.
     */
    private function loop(PendingAwaitable $until): void
    {
        while (!$until->isSettled()) {
            $this->expireTimers();
            $this->wake();

            if ($this->ready !== []) {
                $fiber = array_shift($this->ready);

                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                $this->release($fiber);

                continue;
            }

            if ($this->timers !== []) {
                $this->sleepUntilNextTimer();

                continue;
            }

            throw new WorkflowRuntimeException(
                'Deadlock: the awaited value cannot settle because no scheduled work remains.'
            );
        }
    }

    /**
     * Record a fiber's state after it yielded control back to the scheduler.
     */
    private function release(Fiber $fiber): void
    {
        if ($fiber->isTerminated()) {
            $this->owned->detach($fiber);
        }
    }

    /**
     * Move fibers whose awaited value has settled back into the ready queue.
     */
    private function wake(): void
    {
        foreach ($this->blocked as $key => $entry) {
            if ($entry['awaitable']->isSettled()) {
                unset($this->blocked[$key]);

                $this->ready[] = $entry['fiber'];
            }
        }
    }

    private function expireTimers(): void
    {
        $now = microtime(true);

        foreach ($this->timers as $key => $timer) {
            if ($timer['at'] <= $now) {
                unset($this->timers[$key]);

                $timer['awaitable']->fulfill(null);
            }
        }
    }

    private function sleepUntilNextTimer(): void
    {
        $next = min(array_column($this->timers, 'at'));
        $wait = $next - microtime(true);

        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }
    }
}
