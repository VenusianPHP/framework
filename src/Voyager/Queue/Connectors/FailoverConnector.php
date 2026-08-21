<?php

namespace Voyager\Queue\Connectors;

use Voyager\Contracts\Events\Dispatcher;
use Voyager\Queue\FailoverQueue;
use Voyager\Queue\QueueManager;

class FailoverConnector implements ConnectorInterface
{
    /**
     * Create a new connector instance.
     */
    public function __construct(
        protected QueueManager $manager,
        protected Dispatcher $events
    ) {
    }

    /**
     * Establish a queue connection.
     *
     * @return \Voyager\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new FailoverQueue(
            $this->manager,
            $this->events,
            $config['connections'],
        );
    }
}
