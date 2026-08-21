<?php

namespace Voyager\System\Console;

use Voyager\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:cast')]
class CastMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:cast';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new custom Instrument cast class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Cast';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->option('inbound')
            ? $this->resolveStubPath('/stubs/cast.inbound.stub')
            : $this->resolveStubPath('/stubs/cast.stub');
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
        return $rootNamespace.'\Casts';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the cast already exists'],
            ['inbound', null, InputOption::VALUE_NONE, 'Generate an inbound cast class'],
        ];
    }
}
