<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'optimize:clear')]
class OptimizeClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'optimize:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Remove the cached bootstrap files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->components->info('Clearing cached bootstrap files.');

        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))
            ->map(fn ($except) => trim($except))
            ->filter()
            ->unique()
            ->flip();

        $tasks = Collection::wrap($this->getOptimizeClearTasks())
            ->reject(fn ($command, $key) => $exceptions->hasAny([$command, $key]))
            ->toArray();

        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn () => $this->callSilently($command) == 0);
        }

        $this->newLine();
    }

    /**
     * Get the commands that should be run to clear the "optimization" files.
     *
     * @return array
     */
    public function getOptimizeClearTasks(): array
    {
        return [
            'config' => 'config:clear',
            'cache' => 'cache:clear',
            'compiled' => 'clear-compiled',
            'events' => 'event:clear',
            ...ServiceProvider::$optimizeClearCommands,
        ];
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['except', 'e', InputOption::VALUE_OPTIONAL, 'The commands to skip'],
        ];
    }
}
