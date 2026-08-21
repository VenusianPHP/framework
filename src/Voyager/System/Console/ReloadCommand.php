<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'reload')]
class ReloadCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'reload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Reload running services';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->components->info('Reloading services.');

        $exceptions = Collection::wrap(explode(',', $this->option('except') ?? ''))
            ->map(fn ($except) => trim($except))
            ->filter()
            ->unique()
            ->flip();

        $tasks = Collection::wrap($this->getReloadTasks())
            ->reject(fn ($command, $key) => $exceptions->hasAny([$command, $key]))
            ->toArray();

        foreach ($tasks as $description => $command) {
            $this->components->task($description, fn () => $this->callSilently($command) == 0);
        }

        $this->newLine();
    }

    /**
     * Get the commands that should be reloaded.
     *
     * @return array
     */
    public function getReloadTasks(): array
    {
        return [
            // 'queue' => 'queue:restart',   // lands with Queue in wave 5
            'schedule' => 'schedule:interrupt',
            ...ServiceProvider::$reloadCommands,
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
