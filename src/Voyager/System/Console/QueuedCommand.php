<?php

namespace Voyager\System\Console;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Console\Kernel as KernelContract;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Bus\Dispatchable;

class QueuedCommand implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * The data to pass to the Computer command.
     *
     * @var array
     */
    protected array $data;

    /**
     * Create a new job instance.
     *
     * @param  array  $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Handle the job.
     *
     * @param  \Voyager\Contracts\Console\Kernel  $kernel
     * @return void
     */
    public function handle(KernelContract $kernel): void
    {
        $kernel->call(...array_values($this->data));
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName(): string
    {
        return array_values($this->data)[0];
    }
}
