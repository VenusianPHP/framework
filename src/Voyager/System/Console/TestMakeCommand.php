<?php

namespace Voyager\System\Console;

use Voyager\Console\GeneratorCommand;
use Voyager\NutsAndBolts\DataObjects\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

#[AsCommand(name: 'make:test')]
class TestMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new test class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Test';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        $suffix = $this->option('unit') ? '.unit.stub' : '.stub';

        return $this->usingPest()
            ? $this->resolveStubPath('/stubs/pest'.$suffix)
            : $this->resolveStubPath('/stubs/test'.$suffix);
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
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return base_path('tests').str_replace('\\', '/', $name).'.php';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        if ($this->option('unit')) {
            return $rootNamespace.'\Unit';
        } else {
            return $rootNamespace.'\Feature';
        }
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'Tests';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the test even if the test already exists'],
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test'],
            ['pest', null, InputOption::VALUE_NONE, 'Create a Pest test'],
            ['phpunit', null, InputOption::VALUE_NONE, 'Create a PHPUnit test'],
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->isReservedName($this->getNameInput()) || $this->didReceiveOptions($input)) {
            return;
        }

        $type = select('Which type of test would you like?', [
            'feature' => 'Feature',
            'unit' => 'Unit',
        ]);

        match ($type) {
            'feature' => null,
            'unit' => $input->setOption('unit', true),
        };
    }

    /**
     * Determine if Pest is being used by the application.
     *
     * @return bool
     */
    protected function usingPest(): bool
    {
        if ($this->option('phpunit')) {
            return false;
        }

        return $this->option('pest') ||
            (function_exists('\Pest\\version') &&
             file_exists(base_path('tests').'/Pest.php'));
    }
}
