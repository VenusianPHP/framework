<?php

namespace Voyager\Queue\Jobs;

use Voyager\Vessel\ControlPanel;
use Voyager\Queue\DatabaseQueue;
use Voyager\Contracts\Queue\Job as JobContract;

class DatabaseJob extends Job implements JobContract
{
    public function __construct(
        ControlPanel $container,
        protected DatabaseQueue $database,
        protected DatabaseJobRecord $job,
        string $connectionName,
        string $queue,
    ) {
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->database->deleteAndRelease($this->queue, $this, $delay);
    }

    public function delete()
    {
        parent::delete();

        $this->database->deleteReserved($this->queue, $this->job->id);
    }

    public function attempts()
    {
        return (int) $this->job->attempts;
    }

    public function getJobId()
    {
        return $this->job->id;
    }

    public function getRawBody(): string
    {
        return $this->job->payload;
    }

    public function getJobRecord(): DatabaseJobRecord
    {
        return $this->job;
    }

    public function getDatabaseQueue(): DatabaseQueue
    {
        return $this->database;
    }
}
