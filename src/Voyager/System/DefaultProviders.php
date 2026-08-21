<?php

namespace Voyager\System;

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
            \Voyager\Cache\CacheServiceProvider::class,
            \Voyager\Concurrency\ConcurrencyServiceProvider::class,
            \Voyager\System\Providers\ConsoleSupportServiceProvider::class,
            \Voyager\Database\DatabaseServiceProvider::class,
            \Voyager\Database\MigrationServiceProvider::class,
            \Voyager\Encryption\EncryptionServiceProvider::class,
            \Voyager\Events\EventServiceProvider::class,
            \Voyager\Filesystem\FilesystemServiceProvider::class,
            \Voyager\System\Providers\FoundationServiceProvider::class,
            \Voyager\Hashing\HashServiceProvider::class,
            \Voyager\Log\LogServiceProvider::class,
            // Pagination needs no provider; its only one loaded blade views and
            // resolved the current page from an HTTP request.
            \Voyager\Pipeline\PipelineServiceProvider::class,
            // Process needs no provider; its Factory auto-resolves.
            \Voyager\Queue\QueueServiceProvider::class,
            \Voyager\Redis\RedisServiceProvider::class,
            \Voyager\Translation\TranslationServiceProvider::class,
            \Voyager\Validation\ValidationServiceProvider::class,
            \Voyager\Broadcasting\BroadcastServiceProvider::class,
            \Voyager\Notifications\NotificationServiceProvider::class,
            // \Voyager\Sketches\SketchesServiceProvider::class,           // wave 7
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
        return new static((new Collection($this->providers))
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
}
