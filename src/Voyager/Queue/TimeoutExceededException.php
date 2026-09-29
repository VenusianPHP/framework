<?php

namespace Voyager\Queue;

class TimeoutExceededException extends MaxAttemptsExceededException
{
    /**
     * Create a new instance for the job.
     *
     * @param  \Voyager\Contracts\Queue\Job  $job
     * @return static
     */
    public static function forJob($job)
    {
        return tap(new static($job->resolveName().' has timed out.'), function ($e) use ($job) {
            $e->job = $job;
        });
    }
}
