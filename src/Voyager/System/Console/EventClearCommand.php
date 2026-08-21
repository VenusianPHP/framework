<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:clear')]
class EventClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'event:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Clear all cached events and listeners';

    /**
     * The filesystem instance.
     *
     * @var \Voyager\Filesystem\Filesystem
     */
    protected \Voyager\Filesystem\Filesystem $files;

    /**
     * Create a new config clear command instance.
     *
     * @param  \Voyager\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function handle(): void
    {
        $this->files->delete($this->venusian->getCachedEventsPath());

        $this->components->info('Cached events cleared successfully.');
    }
}
