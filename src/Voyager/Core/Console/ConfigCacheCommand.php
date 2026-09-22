<?php

namespace Voyager\Core\Console;

use LogicException;
use Throwable;
use Voyager\Console\Command;
use Voyager\Filesystem\Filesystem;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'config:cache')]
class ConfigCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'config:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a cache file for faster configuration loading';

    /**
     * The filesystem instance.
     *
     * @var \Voyager\Filesystem\Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new config cache command instance.
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
     * @throws \LogicException
     */
    public function handle(): void
    {
        $this->callSilent('config:clear');

        $config = $this->getFreshConfiguration();

        $configPath = $this->venusian->getCachedConfigPath();

        $this->files->put(
            $configPath, '<?php return '.var_export($config, true).';'.PHP_EOL
        );

        try {
            require $configPath;
        } catch (Throwable $e) {
            $this->files->delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        $this->components->info('Configuration cached successfully.');
    }

    /**
     * Boot a fresh copy of the application configuration.
     *
     * The running app already answered configurationIsCached() once and that answer is
     * memoised, so it can't be reused: a second boot reads config/ from disk for real.
     *
     * @return array
     */
    protected function getFreshConfiguration(): array
    {
        $app = require $this->venusian->bootstrapPath().'/app.php';

        $app->make(ConsoleKernel::class)->bootstrap();

        return $app['config']->all();
    }
}
