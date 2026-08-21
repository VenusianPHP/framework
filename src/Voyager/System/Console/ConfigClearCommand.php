<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'config:clear')]
class ConfigClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'config:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Remove the configuration cache file';

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
     */
    public function handle(): void
    {
        $this->files->delete($this->venusian->getCachedConfigPath());

        $this->components->info('Configuration cache cleared successfully.');
    }
}
