<?php

namespace Voyager\System\Console;

use Voyager\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:rule')]
class RuleMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:rule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new validation rule';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Rule';

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Voyager\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass(string $name): string
    {
        return str_replace(
            '{{ ruleType }}',
            $this->option('implicit') ? 'ImplicitRule' : 'Rule',
            parent::buildClass($name)
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        $stub = $this->option('implicit')
            ? '/stubs/rule.implicit.stub'
            : '/stubs/rule.stub';

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
        return $rootNamespace.'\Rules';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the rule already exists'],
            ['implicit', 'i', InputOption::VALUE_NONE, 'Generate an implicit rule'],
        ];
    }
}
