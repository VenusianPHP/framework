<?php

namespace Voyager\Queue\Console;

use Voyager\Console\Command;
use Voyager\Queue\Failed\PrunableFailedJobProvider;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:prune-failed')]
class PruneFailedJobsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected ?string $signature = 'queue:prune-failed
                {--hours=24 : The number of hours to retain failed jobs data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Prune stale entries from the failed jobs table';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $failer = $this->venusian['queue.failer'];

        if ($failer instanceof PrunableFailedJobProvider) {
            $count = $failer->prune(Carbon::now()->subHours($this->option('hours')));
        } else {
            $this->components->error('The ['.class_basename($failer).'] failed job storage driver does not support pruning.');

            return 1;
        }

        $this->components->info("{$count} entries deleted.");
    }
}
