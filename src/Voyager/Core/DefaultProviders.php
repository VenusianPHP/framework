<?php

namespace Voyager\Core;

use Voyager\NutsAndBolts\Collection;

class DefaultProviders
{
    /**
     * The current providers.
     *
     * @var array<class-string>
     */
    protected array $providers;

    /**
     * Create a new default provider collection.
     *
     * Commented entries are components this port has not reached yet. They are
     * left in place, in order, so landing one is a matter of uncommenting.
     *
     * @param  array<class-string>|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [
            \Voyager\Bus\BusServiceProvider::class,
            \Voyager\Core\Providers\ConsoleSupportServiceProvider::class,
            \Voyager\Cache\CacheServiceProvider::class,
            \Voyager\Concurrency\ConcurrencyServiceProvider::class,
            \Voyager\Database\DatabaseServiceProvider::class,
            \Voyager\Database\MigrationServiceProvider::class,
            \Voyager\Filesystem\FilesystemServiceProvider::class,
            \Voyager\Core\Providers\FoundationServiceProvider::class,
            \Voyager\Hashing\HashServiceProvider::class,
            \Voyager\Http\HttpServiceProvider::class,
            \Voyager\Log\LogServiceProvider::class,
            \Voyager\Queue\QueueServiceProvider::class,
            \Voyager\Redis\RedisServiceProvider::class,
            \Voyager\Broadcasting\BroadcastServiceProvider::class,
            \Voyager\IOPools\IOPoolsServiceProvider::class,
            \Voyager\Pipeline\PipelineServiceProvider::class,
            \Voyager\Sketches\SketchesServiceProvider::class,
            \Voyager\Workflows\WorkflowsServiceProvider::class,
        ];
    }

    /**
     * Merge the given providers into the provider collection.
     *
     * @param  array<class-string>  $providers
     * @return static
     */
    public function merge(array $providers): static
    {
        $this->providers = array_merge($this->providers, $providers);

        return new static($this->providers);
    }

    /**
     * Replace the given providers with other providers.
     *
     * @param  array<class-string, class-string>  $replacements
     * @return static
     */
    public function replace(array $replacements): static
    {
        $current = new Collection($this->providers);

        foreach ($replacements as $from => $to) {
            $key = $current->search($from);

            $current = is_int($key) ? $current->replace([$key => $to]) : $current;
        }

        return new static($current->values()->toArray());
    }

    /**
     * Disable the given providers.
     *
     * @param  array<class-string>  $providers
     * @return static
     */
    public function except(array $providers): static
    {
        return new static(new Collection($this->providers)
            ->reject(fn ($p) => in_array($p, $providers))
            ->values()
            ->toArray());
    }

    /**
     * Convert the provider collection to an array.
     *
     * @return array<class-string>
     */
    public function toArray(): array
    {
        return $this->providers;
    }

    /**
     * Get the default providers for a Venusian application.
     *
     * System owns this list (composition root) — not NutsAndBolts\ServiceProvider.
     *
     * @return static
     */
    public static function make(): static
    {
        return new static;
    }
}