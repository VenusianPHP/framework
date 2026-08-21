<?php

namespace Voyager\System\Console;

use Voyager\Console\Command;
use Voyager\System\Support\Providers\EventServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:cache')]
class EventCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'event:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = "Discover and cache the application's events and listeners";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->callSilent('event:clear');

        file_put_contents(
            $this->venusian->getCachedEventsPath(),
            '<?php return '.var_export($this->getEvents(), true).';'
        );

        $this->components->info('Events cached successfully.');
    }

    /**
     * Get every event and listener configured for the application.
     *
     * @return array
     */
    protected function getEvents(): array
    {
        $events = [];

        foreach ($this->venusian->getProviders(EventServiceProvider::class) as $provider) {
            $providerEvents = array_merge_recursive($provider->shouldDiscoverEvents() ? $provider->discoverEvents() : [], $provider->listens());

            $events[get_class($provider)] = $providerEvents;
        }

        return $events;
    }
}
