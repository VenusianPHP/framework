<?php

namespace Voyager\Database\Console\Seeds;

use Voyager\Console\GeneratorCommand;
use Voyager\NutsAndBolts\DataObjects\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:seeder')]
class SeederMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:seeder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new seeder class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Seeder';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): ?bool
    {
        parent::handle();
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/seeder.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return is_file($customPath = $this->venusian->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name): string
    {
        $name = str_replace('\\', '/', Str::replaceFirst($this->rootNamespace(), '', $name));

        if (is_dir($this->venusian->databasePath().'/seeds')) {
            return $this->venusian->databasePath().'/seeds/'.$name.'.php';
        }

        return $this->venusian->databasePath().'/seeders/'.$name.'.php';
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return 'Database\Seeders\\';
    }
}
