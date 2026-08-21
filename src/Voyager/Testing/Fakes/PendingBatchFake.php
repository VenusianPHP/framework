<?php

namespace Voyager\Testing\Fakes;

use Closure;
use Voyager\Bus\PendingBatch;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Concerns\ReflectsClosures;

class PendingBatchFake extends PendingBatch
{
    use ReflectsClosures;

    /**
     * The fake bus instance.
     *
     * @var \Voyager\Testing\Fakes\BusFake
     */
    protected $bus;

    /**
     * Create a new pending batch instance.
     *
     * @param  \Voyager\Testing\Fakes\BusFake  $bus
     * @param  \Voyager\NutsAndBolts\Collection  $jobs
     */
    public function __construct(BusFake $bus, Collection $jobs)
    {
        $this->bus = $bus;
        $this->jobs = $jobs->filter()->values();
    }

    /**
     * Dispatch the batch.
     *
     * @return \Voyager\Bus\Batch
     */
    public function dispatch(): ?\Voyager\Bus\Batch
    {
        return $this->bus->recordPendingBatch($this);
    }

    /**
     * Dispatch the batch after the response is sent to the browser.
     *
     * @return \Voyager\Bus\Batch
     */
    public function dispatchAfterResponse(): ?\Voyager\Bus\Batch
    {
        return $this->bus->recordPendingBatch($this);
    }

    /**
     * Determine if the jobs in the batch match the given jobs.
     *
     * @param  array  $expectedJobs
     * @return bool
     */
    public function hasJobs(array $expectedJobs)
    {
        if (count($this->jobs) !== count($expectedJobs)) {
            return false;
        }

        foreach ($expectedJobs as $index => $expectedJob) {
            if ($expectedJob instanceof Closure) {
                $expectedType = $this->firstClosureParameterType($expectedJob);

                if (! $this->jobs[$index] instanceof $expectedType) {
                    return false;
                }

                if (! $expectedJob($this->jobs[$index])) {
                    return false;
                }
            } elseif (is_string($expectedJob)) {
                if ($expectedJob != get_class($this->jobs[$index])) {
                    return false;
                }
            } elseif (serialize($expectedJob) != serialize($this->jobs[$index])) {
                return false;
            }
        }

        return true;
    }
}
