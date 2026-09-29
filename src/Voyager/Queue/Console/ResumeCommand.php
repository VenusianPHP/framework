<?php

namespace Voyager\Queue\Console;

use Voyager\Console\Command;
use Voyager\Contracts\Queue\Factory as QueueManager;
use Voyager\Queue\Console\Concerns\ParsesQueue;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:resume', aliases: ['queue:continue'])]
class ResumeCommand extends Command
{
    use ParsesQueue;

    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $signature = 'queue:resume {queue : The name of the queue that should resume processing}';

    /**
     * The console command name aliases.
     *
     * @var list<string>
     */
    protected ?array $aliases = ['queue:continue'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Resume job processing for a paused queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(QueueManager $manager)
    {
        [$connection, $queue] = $this->parseQueue($this->argument('queue'));

        $manager->resume($connection, $queue);

        $this->components->info("Job processing on queue [{$connection}:{$queue}] has been resumed.");

        return 0;
    }
}
