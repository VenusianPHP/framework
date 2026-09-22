<?php

namespace Voyager\Core\Signals;

use Voyager\Contracts\Signals\Signal;

/**
 * Fired before stub:publish writes anything, so a listener can add to or drop from the list.
 */
class PublishingStubs implements Signal
{
    /**
     * @param array<string, string> $stubs source path => destination name
     */
    public function __construct(
        public array $stubs = [],
    ) {}

    /**
     * Add a stub to the list about to be published.
     *
     * @return $this
     */
    public function add(string $path, string $name): static
    {
        $this->stubs[$path] = $name;

        return $this;
    }
}
