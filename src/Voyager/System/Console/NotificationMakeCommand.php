<?php

namespace Voyager\System\Console;

use Voyager\Console\Concerns\CreatesMatchingTest;
use Voyager\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(name: 'make:notification')]
class NotificationMakeCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a new notification class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected ?string $type = 'Notification';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): ?bool
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return null;
        }

        return null;
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass(string $name): string
    {
        return parent::buildClass($name);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/notification.stub');
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
        return $rootNamespace.'\Notifications';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the notification already exists'],
        ];
    }
}
