<?php

namespace Voyager\Contracts\Bus;

interface QueueingDispatcher extends Dispatcher
{
    /**
     * Attempt to find the batch with the given ID.
     *
     * @param  string  $batchId
     * @return \Voyager\Bus\Batch|null
     */
    public function findBatch(string $batchId);

    /**
     * Create a new batch of queueable jobs.
     *
     * @param  \Voyager\NutsAndBolts\Collection|array  $jobs
     * @return \Voyager\Bus\PendingBatch
     */
    public function batch(\Voyager\NutsAndBolts\Collection|array $jobs);

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue(mixed $command);
}
