<?php

namespace Voyager\System\Console;

use Voyager\Console\GeneratorCommand;
use Voyager\NutsAndBolts\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:provider')]
class ProviderMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:provider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new service provider class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Provider';

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Voyager\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result === false) {
            return $result;
        }

        ServiceProvider::addProviderToBootstrapFile(
            $this->qualifyClass($this->getNameInput()),
            $this->venusian->getBootstrapProvidersPath(),
        );

        return $result;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/provider.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->venusian->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace.'\Providers';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the provider already exists'],
        ];
    }
}
