<?php

namespace Voyager\Queue\Events;

class JobPopped
{
    /**
     * Create a new event instance.
     *
     * @param  string  $connectionName  The connection name.
     * @param  \Voyager\Contracts\Queue\Job|null  $job  The job instance.
     */
    public function __construct(
        public $connectionName,
        public $job,
    ) {
    }
}
