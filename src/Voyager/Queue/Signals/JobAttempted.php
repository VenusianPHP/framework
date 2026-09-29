<?php

namespace Voyager\Queue\Signals;

use Voyager\Contracts\Signals\Signal;

class JobAttempted implements Signal
{
    /**
     * Create a new event instance.
     *
     * @param  string  $connectionName  The connection name.
     * @param  \Voyager\Contracts\Queue\Job  $job  The job instance.
     * @param  bool  $exceptionOccurred  Indicates if an exception occurred while processing the job.
     */
    public function __construct(
        public $connection_name,
        public $job,
        public $exception_occurred = false,
    ) {
    }

    /**
     * Determine if the job completed with failing or an unhandled exception occurring.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return ! $this->job->hasFailed() && ! $this->exception_occurred;
    }
}
