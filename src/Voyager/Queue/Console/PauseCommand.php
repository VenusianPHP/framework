<?php

namespace Voyager\Queue\Console;

use Voyager\Console\Command;
use Voyager\Contracts\Queue\Factory as QueueManager;
use Voyager\Queue\Console\Concerns\ParsesQueue;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:pause')]
class PauseCommand extends Command
{
    use ParsesQueue;

    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $signature = 'queue:pause {queue : The name of the queue to pause}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Pause job processing for a specific queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(QueueManager $manager)
    {
        [$connection, $queue] = $this->parseQueue($this->argument('queue'));

        $manager->pause($connection, $queue);

        $this->components->info("Job processing on queue [{$connection}:{$queue}] has been paused.");

        return 0;
    }
}
