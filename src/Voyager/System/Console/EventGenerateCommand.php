<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\System\Support\Providers\EventServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:generate')]
class EventGenerateCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'event:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Generate the missing events and listeners based on registration';

    /**
     * Indicates whether the command should be shown in the Computer command list.
     *
     * @var bool
     */
    protected bool $hidden = true;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $providers = $this->venusian->getProviders(EventServiceProvider::class);

        foreach ($providers as $provider) {
            foreach ($provider->listens() as $event => $listeners) {
                $this->makeEventAndListeners($event, $listeners);
            }
        }

        $this->components->info('Events and listeners generated successfully.');
    }

    /**
     * Make the event and listeners for the given event.
     *
     * @param  string  $event
     * @param  array  $listeners
     * @return void
     */
    protected function makeEventAndListeners(string $event, array $listeners): void
    {
        if (! str_contains($event, '\\')) {
            return;
        }

        $this->callSilent('make:event', ['name' => $event]);

        $this->makeListeners($event, $listeners);
    }

    /**
     * Make the listeners for the given event.
     *
     * @param  string  $event
     * @param  array  $listeners
     * @return void
     */
    protected function makeListeners(string $event, array $listeners): void
    {
        foreach ($listeners as $listener) {
            $listener = preg_replace('/@.+$/', '', $listener);

            $this->callSilent('make:listener', array_filter(
                ['name' => $listener, '--event' => $event]
            ));
        }
    }
}
