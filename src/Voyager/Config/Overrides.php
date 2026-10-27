<?php

namespace Voyager\Config;

use Voyager\NutsAndBolts\DataObjects\Arr;

/**
 * Configuration writes kept apart from the repository's items, read over them. The effective
 * items are built once and reused until this records a write or the base items change.
 */
class Overrides
{
    /**
     * Dot-notation key => value, oldest first. Writing a key again moves it to the end, so a
     * replay applies writes in the order they were last made.
     *
     * @var array<string, mixed>
     */
    private array $writes = [];

    /**
     * The base items with every write applied, or null when it must be built again.
     *
     * @var array<string, mixed>|null
     */
    private ?array $effective = null;

    /**
     * The base version the effective items were built against.
     */
    private int $built_against = -1;

    /**
     * How many times the effective items were built.
     */
    private int $builds = 0;

    /**
     * Record a write.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function record(string $key, mixed $value): void
    {
        unset($this->writes[$key]);

        $this->writes[$key] = $value;
        $this->effective = null;
    }

    /**
     * Determine if no write was recorded.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->writes === [];
    }

    /**
     * The base items with every recorded write applied.
     *
     * @param array<string, mixed> $base
     * @param int $version the base's version: a different one builds the items again
     * @return array<string, mixed>
     */
    public function over(array $base, int $version): array
    {
        if ($this->effective === null || $this->built_against !== $version) {
            foreach ($this->writes as $key => $value) {
                Arr::set($base, $key, $value);
            }

            $this->effective = $base;
            $this->built_against = $version;
            $this->builds++;
        }

        return $this->effective;
    }

    /**
     * How many times the effective items were built.
     *
     * @return int
     */
    public function builds(): int
    {
        return $this->builds;
    }
}
