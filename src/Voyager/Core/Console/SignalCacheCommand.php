<?php

namespace Voyager\Core\Console;

use Voyager\Console\Command;
use Voyager\Core\Providers\SignalServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'signal:cache')]
class SignalCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'signal:cache';

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
        $this->callSilent('signal:clear');

        file_put_contents(
            $this->venusian->getCachedSignalsPath(),
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

        foreach ($this->venusian->getProviders(SignalServiceProvider::class) as $provider) {
            $providerEvents = array_merge_recursive($provider->shouldDiscoverEvents() ? $provider->discoverSignals() : [], $provider->listens());

            $events[get_class($provider)] = $providerEvents;
        }

        return $events;
    }
}
